# negation_only: drop the refusal of an access level made only of negations, which is the exact
# rule this stage exists to add. The unknown-capability branch beside it is left alone, so a test
# that reddens here is holding the negation rule and not accesslevel validation in general.
#
# The else-if is turned into a dead branch rather than deleted, so the surrounding shape — and the
# $entries / $positives counters feeding it — stay in place and the mutation is exactly one rule.
#
# Note the bracket delimiter on the replacement: it carries unbalanced braces of its own, which a
# s{...}{...} form cannot express.
s{\} else if \(\$entries > 0 && \$positives === 0\) \{}[} else if (false) {];
