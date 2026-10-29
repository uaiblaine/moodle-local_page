# editor_own_area: a page's draft is prepared from the shared item id 0 again.
s{        return \$pageid > 0 \? \$pageid : null;}{        return \$this->pagecontext->contextlevel == CONTEXT_COURSECAT ? (\$pageid > 0 ? \$pageid : null) : 0;};
