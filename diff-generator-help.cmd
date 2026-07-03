Generating only changes, no lines, no any other thing:


git diff --no-index -U0 -w patches/originals/bosta-woocommerce.php plugins/bosta-woocommerce/bosta-woocommerce.php | grep -v '^[+][[:space:]]*$' > bosta-changes.patch


git diff --no-index -U0 -w patches/originals/woo.php themes/xstore/framework/woo.php | grep -v '^[+][[:space:]]*$' > xstore-changes.patch

