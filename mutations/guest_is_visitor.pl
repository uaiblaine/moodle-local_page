# guest_is_visitor: stop counting the guest account as a visitor. Core treats a guest session as logged
# in, so this is the misreading "logged in" invites — and it lets anybody who presses "Access as a
# guest" past the one refusal a visitor gets, into every category whatever its state.
s{return !isloggedin\(\) \|\| isguestuser\(\);}{return !isloggedin();}s;
