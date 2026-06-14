Generating only changes, no lines, no any other thing:


git diff --no-index -U0 -w originals/bosta-woocommerce.php plugins/bosta-woocommerce/bosta-woocommerce.php | grep -v '^[+][[:space:]]*$'

to output result pipe it to file: > bosta-changes.patch
