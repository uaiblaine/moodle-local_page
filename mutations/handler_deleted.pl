# handler_deleted: let the shortlink handler resolve the code of a deleted page, so /p/<code> keeps
# sending people to a page that no longer exists — and, for a page deleted by any route that did not
# forget its codes, to whatever its address serves next.
s{\['id' => \(int\) \$identifier, 'deleted' => 0\]}{['id' => (int) \$identifier]}s;
