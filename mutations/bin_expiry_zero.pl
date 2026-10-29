# bin_expiry_zero: a retention of 0 no longer keeps pages; every deleted page is past its time.
s~        if \(\$expiry > 0\) \{~        if (true) {~;
