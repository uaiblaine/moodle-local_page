# route_requirelogin: make the routed category address require a login. The page serves visitors of a
# public category on a site that forces login and decides for itself who reads what; a login
# requirement on the route refuses every visitor before that decision is ever asked.
s{(        method: \['GET'\],\n)}{$1        requirelogin: new \\core\\router\\require_login(requirelogin: true),\n}s;
