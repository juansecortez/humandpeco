<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // Deshabilita FKs en SQL Server
        DB::statement('EXEC sp_MSforeachtable "ALTER TABLE ? NOCHECK CONSTRAINT ALL"');

        // Elimina datos y resetea identidad
        $tables = ['item_tag', 'items', 'tags', 'categories', 'users', 'roles'];
        foreach ($tables as $t) {
            DB::statement("DELETE FROM [$t]");
            DB::statement("
                IF EXISTS (
                    SELECT 1
                    FROM sys.identity_columns
                    WHERE object_id = OBJECT_ID('$t')
                )
                DBCC CHECKIDENT ('$t', RESEED, 0)
            ");
        }

        // Ejecutar seeders
        $this->call([
            RolesTableSeeder::class,
            UsersTableSeeder::class,
            TagsTableSeeder::class,
            CategoriesTableSeeder::class,
            ItemsTableSeeder::class,
        ]);

        // Rehabilita FKs en SQL Server
        DB::statement('EXEC sp_MSforeachtable "ALTER TABLE ? WITH CHECK CHECK CONSTRAINT ALL"');
    }
}
