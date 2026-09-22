# form_action_context: let moodleform pick the action itself. Its default is
# strip_querystring($FULLME), so the query string is discarded and the editor posts back to a bare
# edit.php — which resolves the SYSTEM context from a URL that names nothing and refuses a category
# author on local/page:addpages. The page was never saved and the hidden contextid was never read.
s{parent::__construct\(local_page_edit_url\(\$this->pagecontext, \(int\) \$this->callingpage\)\);}{parent::__construct();};
