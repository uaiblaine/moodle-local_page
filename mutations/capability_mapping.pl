# capability_mapping: make scope::capability() answer local/page:addpages at every context level.
# One capability would then govern both scopes, so a site-wide editor would author and preview
# every category's pages while a category manager could author none — the split this stage exists
# to make would be gone, with every call site still reading as though it were there.
s{        if \(\$context->contextlevel == CONTEXT_SYSTEM\) \{\n            return 'local/page:addpages';\n        \}\n\n        if \(\$context->contextlevel == CONTEXT_COURSECAT\) \{\n            return 'local/page:managecategorypages';\n        \}}{        return 'local/page:addpages';}s;
