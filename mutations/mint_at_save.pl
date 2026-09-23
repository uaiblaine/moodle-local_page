# mint_at_save: stop the save path minting a category page's public short address. Nothing else mints
# one — a GET never does — so every category page would be left without an address to share.
s{                if \(\$iscategory && \\local_page\\local\\links::routing_enabled\(\)\) \{\n                    \\local_page\\local\\links::share\([^\n]*\n                \}\n}{}s;
