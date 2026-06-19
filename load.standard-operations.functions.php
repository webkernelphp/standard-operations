<?php declare(strict_types=1);

$classmap_dir = __DIR__ . '/ops';
if (is_dir($classmap_dir)) {
        $files = glob($classmap_dir . '/*.ope.php');
        if ($files !== false) { foreach ($files as $file) { require $file; }
    }
}
