<?php
/**
 * Quick test script to debug the plans API endpoint
 * Run this from command line: php test-plans-api.php
 */

// Check if the route exists
echo "Testing Plans API Routes...\n\n";

// Test 1: Check if route is registered
echo "1. Checking route registration:\n";
$routeList = shell_exec('php artisan route:list --name=plans.optimized.global.datatable');
echo $routeList;
echo "\n";

// Test 2: Check if controller exists
echo "2. Checking if ApiPlansController exists:\n";
$controllerPath = __DIR__ . '/app/Http/Controllers/Api/PlansController.php';
if (file_exists($controllerPath)) {
    echo "✓ Controller file exists\n";
} else {
    echo "✗ Controller file NOT found\n";
}
echo "\n";

// Test 3: Check if service exists
echo "3. Checking if PlanService exists:\n";
$servicePath = __DIR__ . '/app/Services/Plan/PlanService.php';
if (file_exists($servicePath)) {
    echo "✓ Service file exists\n";
} else {
    echo "✗ Service file NOT found\n";
}
echo "\n";

// Test 4: Check for syntax errors
echo "4. Checking for PHP syntax errors:\n";
$syntaxCheck = shell_exec("php -l $controllerPath 2>&1");
echo $syntaxCheck;
echo "\n";

$syntaxCheck2 = shell_exec("php -l $servicePath 2>&1");
echo $syntaxCheck2;
echo "\n";

echo "Test complete!\n";
