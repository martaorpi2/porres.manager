<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ResetEducationalData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:reset-educational {--fresh : Ejecutar migraciones frescas antes de sembrar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Limpia la base de datos (excepto users) y genera datos educativos y de salud';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Iniciando proceso de limpieza y generación de datos educativos...');

        if ($this->option('fresh')) {
            $this->info('📋 Ejecutando migraciones frescas...');
            Artisan::call('migrate:fresh');
            $this->info('✅ Migraciones frescas completadas.');
        }

        $this->info('🧹 Limpiando base de datos (excepto tabla users)...');
        Artisan::call('db:seed', ['--class' => 'CleanDatabaseSeeder']);
        $this->info('✅ Base de datos limpiada.');

        $this->info('🏥 Generando datos educativos y de salud...');
        Artisan::call('db:seed', ['--class' => 'EducationalHealthDataSeeder']);
        $this->info('✅ Datos educativos y de salud generados.');

        $this->info('🎉 ¡Proceso completado exitosamente!');
        $this->newLine();
        $this->info('📊 Datos generados:');
        $this->line('   • 9 Rubros de proveedores');
        $this->line('   • 5 Sectores (Laboratorio, Hemoterapia, Radiología, etc.)');
        $this->line('   • 5 Proveedores especializados');
        $this->line('   • 10 Categorías de productos');
        $this->line('   • 10 Ubicaciones del instituto');
        $this->line('   • 16 Productos de salud y educación');
        $this->line('   • Niveles de stock, órdenes de compra, aplicaciones y más');
    }
}
