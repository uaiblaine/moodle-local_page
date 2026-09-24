# badge_editors_only: add the status badge to the heading for every reader, not only for somebody who
# may edit the page.
s|if \(\$caneditpage\) \{(\n\s+\$heading \.= )|if (true) {$1|;
