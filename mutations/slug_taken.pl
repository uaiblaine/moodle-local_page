# slug_taken: make is_taken() answer false for everything. Both the form's refusal and the
# re-check inside the save lock read through it, so a page could take an address another live page
# is already serving — and custompage::load_by_menuname() resolves the duplicate with
# ORDER BY id DESC, so the newer page silently takes the URL over.
s{        return \$DB->record_exists_select\('local_page', \$select, \$params\);}{        return false;};
