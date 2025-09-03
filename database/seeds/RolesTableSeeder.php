<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesTableSeeder extends Seeder
{
    public function run()
    {
        // Detecta esquema (dbo u otro)
        $schema = DB::scalar("
            SELECT s.name
            FROM sys.tables t
            JOIN sys.schemas s ON s.schema_id = t.schema_id
            WHERE t.name = 'roles'
        ") ?? 'dbo';

        // Limpia y resetea identidad
        DB::unprepared("
            DELETE FROM [{$schema}].[roles];
            DBCC CHECKIDENT ('{$schema}.roles', RESEED, 0);
        ");

        // ACTIVAR + INSERTAR + APAGAR en UN SOLO BATCH (sintaxis corregida)
        DB::unprepared("
            SET IDENTITY_INSERT [{$schema}].[roles] ON;

            INSERT INTO [{$schema}].[roles] (id, name, description, created_at, updated_at) VALUES
            (1, 'Admin',      'This is the administration role',    GETDATE(), GETDATE()),
            (2, 'Creator',    'This is the creator role',           GETDATE(), GETDATE()),
            (3, 'Member',     'This is the member role',            GETDATE(), GETDATE()),
            (4, 'Vacaciones', 'This is the vacaciones member role', GETDATE(), GETDATE()),
            (5, 'Nominas',    'This is the nominas member role',    GETDATE(), GETDATE());

            SET IDENTITY_INSERT [{$schema}].[roles] OFF;

            DECLARE @mx INT = (SELECT ISNULL(MAX([id]),0) FROM [{$schema}].[roles]);
            DBCC CHECKIDENT ('{$schema}.roles', RESEED, @mx);
        ");
    }
}
