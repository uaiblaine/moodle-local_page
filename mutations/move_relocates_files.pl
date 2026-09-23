# move_relocates_files: lifecycle::category_moved() re-points the rows but leaves the files in the old
# context, where core purges them with the context once the callback returns.
s{\n                foreach \(self::FILEAREAS as \$filearea\) \{\n                    \$fs->move_area_files_to_new_context\([^\n]*\n                \}\n}{\n};
