# badge_localised: write the live badge's text as a fixed English word instead of asking for the
# plugin's status string, which is what upstream's stylesheet did. Under the stock English strings the
# two read the same, so only a site that words its strings differently can tell them apart.
s|'live' => \[get_string\('status_live', 'local_page'\), |'live' => ['Live', |;
