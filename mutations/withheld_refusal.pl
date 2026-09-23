# withheld_refusal: let request::category() hand a visitor the "no access" page for a page its own
# rules withhold, instead of the login refusal a missing page gets. Inside a public category that
# answers 200 for a draft and a redirect for a slug that names nothing, so an anonymous client can
# list which slugs exist; and a visitor asking for a page kept for logged-in readers is left on a
# page telling them nothing instead of at the login form that would let them in.
s{        if \(\$isvisitor && !\$canview\) \{\n            return self::refuse\(\$url\);\n        \}\n}{}s;
