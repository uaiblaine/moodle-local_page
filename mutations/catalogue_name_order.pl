# Keep the rows' id order instead of the collator's name order.
s{\\core_collator::asort\(\$names\);}{ksort(\$names);};
