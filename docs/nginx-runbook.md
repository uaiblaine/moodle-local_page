# NGINX runbook for custom pages

As of 2026-09-23, for local_page 1.0.10+uai.9 on Moodle 5.2.

**Optional by design.** Category pages need nothing from the web server beyond Moodle's own router
fallback (step 1), which a Moodle 5.2 site with the routing engine configured already has. Steps 2
and 3 are for a site that also wants upstream's root-level friendly URLs for site-wide pages, or a
shorter prefix of its own for category pages. The canonical guidance is the README's *Friendly URLs*
section; this file puts it in order for a production site and pairs each step with the check that
proves it.

How the values below were obtained: every `location` was loaded into NGINX (`nginx:alpine`) in front
of a stub PHP handler that echoes the script and query string it receives, and every `curl`
expectation was measured against a Moodle 5.2 development site. The answers are Moodle's, so they are
the same behind NGINX.

## Before you start

- Set `SITE` to `$CFG->wwwroot`, without a trailing slash, in the shell you run the checks from.
  Run them from outside the VPN, with no session: a logged-in cookie changes every answer.
- Work in the `server` block that serves `$CFG->wwwroot`. On Moodle 5.1 and later its `root` is
  Moodle's `public/` directory.
- Keep the site's existing PHP location. The examples below write it `location ~ \.php$`; whatever
  pattern yours uses, "after the PHP location" below means after that block in the file.
- After every change: `nginx -t && systemctl reload nginx`, then the step's check, before the next
  step. Undoing a step is removing its block and reloading.
- If Moodle lives in a subdirectory, prefix every path below with it.

## 1. The router fallback (required)

```nginx
location / {
    try_files $uri $uri/ /r.php$is_args$args;
}
```

And in `config.php`:

```php
$CFG->routerconfigured = true;
```

`$is_args$args` is the right spelling here because `/r.php` carries no query of its own. The router
reads the original request URI, not the rewritten one, which is how the path survives the fallback;
it is also why step 3 must never rewrite onto a routed path.

Check:

```sh
curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' "$SITE/local_page/category/999999999/router-check"
curl -s -o /dev/null -D - "$SITE/not/a/valid/request" | grep -i '^HTTP\|^x-powered-by'
```

- The first line must read `302 $SITE/login/index.php`. Only Moodle's router answers a plugin's
  route, so this line is the proof. A web server `404` means the fallback is missing, or another
  location catches the path first.
- The second line is `HTTP/1.1 404 Not Found` with `X-Powered-By: PHP/...` where PHP's `expose_php`
  is on. Production servers often switch that header off, and NGINX's own 404 page has the same
  `404 Not Found` title as the router's, so without the header this line proves nothing by itself.
- *Site administration > Reports > System status* runs Moodle's *Router configuration* check, which
  requests three addresses of the site from the server itself.

## 2. Root-level friendly URLs for site-wide pages (optional)

This is upstream's feature: `$SITE/about-us` for a site-wide page whose friendly URL is `about-us`.
Category pages never use it.

```nginx
# Declared AFTER the PHP location.
location ~ ^/([a-zA-Z0-9_-]+)$ {
    try_files $uri $uri/ /local/page/index.php?menuname=$1&$query_string;
}
```

Two traps, both measured:

- **No dot in the character class.** A friendly URL cannot contain one (the form cleans it with
  `PARAM_ALPHANUMEXT`), and a class that admits one also matches `/index.php`. Declared before the PHP
  location, such a block takes the request, `try_files $uri` finds the file, and NGINX sends the PHP
  source as text: `/index.php` and `/r.php` came back beginning with `<?php` under exactly that
  configuration. NGINX takes the first matching regex location in the order they are declared, which
  is why this one goes after the PHP location as well.
- **`&$query_string`, not `$is_args$args`.** The target already has a query, so the visitor's is
  appended with `&` (`$query_string` is another name for `$args`). With `$is_args$args` a shared link
  carrying `?fbclid=abc` reached the script as `menuname=about-us?fbclid=abc`, which the viewer cleans
  to the slug `about-usfbclidabc`: a page that does not exist. Without a query, `&$query_string` leaves
  a trailing `&`, which PHP ignores.

A regex location also out-ranks the `location /` prefix of step 1, so this block captures every
single-segment path that is not a file or a directory, before the router sees it. The editor refuses
friendly URLs that Moodle answers on itself for that reason.

Check, with the friendly URL of a live site-wide page:

```sh
curl -s -o /dev/null -w '%{http_code}\n' "$SITE/about-us"
curl -s -o /dev/null -w '%{http_code}\n' "$SITE/about-us?fbclid=test"
```

Both must answer `200`. A `200` on the first line and anything else on the second is the query
spelling; then run step 4.

## 3. A shorter prefix for category pages (optional)

The plugin ships nothing for this. It maps a vanity prefix onto the script's category address, which
then answers a `303` to the page's routed address, so the address bar settles on the canonical one.

```nginx
# Declared AFTER the PHP location: NGINX takes the first matching regex location in
# declaration order, and this one must never capture a PHP script.
location ~ ^/paginas/(\d+)/([a-z0-9_-]+)$ {
    rewrite ^/paginas/(\d+)/([a-z0-9_-]+)$ /local/page/index.php?category=$1&page=$2 last;
}
```

- Map onto the **query-string** address, never onto `/local_page/category/...`. The router reads the
  original request URI, so an internal rewrite to a routed path is invisible to it: it sees
  `/paginas/...`, a path it has no route for, and answers `404`.
- `rewrite` appends the visitor's query to a replacement that already has one:
  `/paginas/12/handbook?fbclid=abc` reached the script as `category=12&page=handbook&fbclid=abc`.
- The class has no dot, for the reason given in step 2. `/paginas/12/index.php` does not match it and
  goes to the PHP location, like any other `.php` path.

Check, with the public category and live test page of the README's checklist:

```sh
curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' "$SITE/paginas/$CAT/$SLUG"
```

Expected: `303 $SITE/local_page/category/$CAT/$SLUG`. A `404` means the block is missing or declared
where another location wins; a `200` means `$CFG->routerconfigured` is off, and the script serves the
page at the alias itself.

## 4. No location serves PHP source

Run this after every change to the server block, and after every NGINX upgrade:

```sh
for path in index.php local/page/index.php r.php paginas/12/index.php; do
    printf '%s: ' "$path"; curl -s "$SITE/$path" | head -c 5; echo
done
```

None of the lines may print `<?php`. Measured on the development site: `<!DOC` for `index.php` and
`local/page/index.php`, and `<!doc` for `r.php`. A `<?php` is Moodle's source code served to anybody,
configuration paths included: remove the block added last, reload, and read step 2's first trap.

## 5. The anonymous checks

These are step 7 of the README's *Verification checklist*, repeated here so the web server work can be
closed with them. The README prints the addresses to fill in and says what each wrong answer means.

```sh
SITE=https://moodle.example.org    # $CFG->wwwroot, no trailing slash
CAT=12                             # the public category holding the test pages
SLUG=adoption-check                # the live test page's friendly URL
PRIV=13                            # a category that is not public
CODE=AbC12                         # the live test page's short code, after /p/
DRAFTIMG='https://...'             # the draft test page's image address

curl -s -o /dev/null -w '%{http_code}\n' "$SITE/local_page/category/$CAT/$SLUG"
for url in "$SITE/local_page/category/$PRIV/$SLUG" \
           "$SITE/local_page/category/999999999/$SLUG" \
           "$SITE/local_page/category/$CAT/no-such-page"; do
    curl -s -o /dev/null -D - "$url" | grep -iv '^date:\|^set-cookie:' | cksum
done
curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' "$SITE/local_page/category/$PRIV/$SLUG"
curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' "$SITE/local/page/index.php?category=$CAT&page=$SLUG"
curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' "$SITE/p/$CODE"
curl -s -o /dev/null -w '%{http_code}\n' "$DRAFTIMG"
```

| Line | Expected |
|---|---|
| The live page | `200` |
| The three refusals | three identical lines |
| The private category, again | `302 $SITE/login/index.php` |
| The script's address | `303 $SITE/local_page/category/$CAT/$SLUG` |
| `/p/$CODE` | `302 $SITE/local_page/category/$CAT/$SLUG` |
| The draft's image | `404` (a development site with developer debugging answers `500`) |
