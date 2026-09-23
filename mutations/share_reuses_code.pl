# share_reuses_code: mint a fresh code on every call to share() instead of reusing the page's code.
# mint() still reads the oldest row back, so the address returned does not change — what changes is a
# new row in core's shortlink table on every save, for ever.
s{\$code = self::existing_code\(\$pageid\) \?\? self::mint\(\$pageid\);}{\$code = self::mint(\$pageid);}s;
