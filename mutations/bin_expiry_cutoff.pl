# bin_expiry_cutoff: the retention is not subtracted, so every page deleted before now goes.
s{\['cutoff' => \$now - \$expiry\]}{['cutoff' => \$now]};
