<?php

$factories = glob(__DIR__ . '/../database/factories/*Factory.php');
$mismatches = [];

foreach ($factories as $f) {
    $content = file_get_contents($f);
    if (preg_match('/protected\s+\$model\s*=\s*([\\\\\w]+)::class/', $content, $m)) {
        $modelClass = $m[1];
        $modelShort = basename(str_replace('\\', '/', $modelClass));
        $factoryClass = pathinfo($f, PATHINFO_FILENAME);
        $expected = $modelShort . 'Factory';
        if ($factoryClass !== $expected) {
            $mismatches[] = [
                'model_short' => $modelShort,
                'factory_class' => $factoryClass,
                'expected' => $expected,
            ];
            echo "{$modelShort} -> {$factoryClass} (expected: {$expected})\n";
        }
    }
}

echo "\nTotal mismatches: " . count($mismatches) . "\n";
