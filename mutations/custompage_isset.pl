# custompage_isset: take __isset() off custompage. empty() then answers true for every property, and
# local_page_user_can_view_page() reads every access level as unset: a page kept for administrators
# renders for an anonymous visitor at every address. Its file route reads a table row and stays shut,
# which is how the hole hid behind a green suite.
s{\n    /\*\*\n     \* Whether the page data carries a property, for isset\(\) and empty\(\)\..*?\n    public function __isset\(\$item\) \{\n        return isset\(\$this->pagedata->\$item\);\n    \}\n}{}s;
