<?php
/**
 * Boots the Craft project Delivery Slots is installed in, so the tests run against real Craft and Commerce.
 *
 *   php vendor/bin/phpunit -c plugins/delivery-slots/phpunit.xml.dist   (from the project root)
 *
 * CRAFT_PROJECT_ROOT points at another project; by default it’s three folders up (project/plugins/delivery-slots/tests).
 */
$root = getenv('CRAFT_PROJECT_ROOT') ?: dirname(__DIR__, 3);
require $root . '/bootstrap.php';
require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';

spl_autoload_register(function(string $class) {
    $prefix = 'suprim\\deliveryslots\\tests\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

if (!\suprim\deliveryslots\Plugin::getInstance()) {
    fwrite(STDERR, "Delivery Slots isn’t installed in the project at $root.\n");
    exit(1);
}
