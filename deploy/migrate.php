<?php
declare(strict_types=1);

// Inicialização de console sem invocar os comandos que dependem de pcntl.
$backend = realpath($argv[1] ?? __DIR__ . '/../backend');
if (!$backend || !is_file($backend . '/app/Config/Paths.php')) {
    fwrite(STDERR, "Indique o diretório backend da versão a migrar.\n");
    exit(1);
}
define('ENVIRONMENT', 'production');
define('FCPATH', $backend . '/public/');
chdir($backend);
require $backend . '/app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/Boot.php';
CodeIgniter\Boot::bootConsole($paths);
$runner = service('migrations');
$runner->setNamespace(null);
if (!$runner->latest()) {
    fwrite(STDERR, "Falha ao aplicar as migrações.\n");
    exit(1);
}
echo "Migrações concluídas.\n";
