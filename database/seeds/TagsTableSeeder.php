<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TagsTableSeeder extends Seeder
{
    public function run()
    {
        // Detectar esquema real de la tabla tags (dbo u otro)
        $schema = DB::scalar("
            SELECT s.name
            FROM sys.tables t
            JOIN sys.schemas s ON s.schema_id = t.schema_id
            WHERE t.name = 'tags'
        ") ?? 'dbo';

        DB::unprepared("
            -- Limpiar y reseed
            DELETE FROM [{$schema}].[tags];
            DBCC CHECKIDENT ('{$schema}.tags', RESEED, 0);

            -- Activar IDENTITY_INSERT, insertar y desactivar en el mismo batch
            SET IDENTITY_INSERT [{$schema}].[tags] ON;

            INSERT INTO [{$schema}].[tags] (id, name, color, created_at, updated_at) VALUES
            (1, 'Hot',      '#f44336', GETDATE(), GETDATE()),
            (2, 'Trending', '#9c27b0', GETDATE(), GETDATE()),
            (3, 'New',      '#00bcd4', GETDATE(), GETDATE());

            SET IDENTITY_INSERT [{$schema}].[tags] OFF;

            -- Asegurar que el siguiente IDENTITY continúe bien
            DECLARE @mx INT = (SELECT ISNULL(MAX([id]),0) FROM [{$schema}].[tags]);
            DBCC CHECKIDENT ('{$schema}.tags', RESEED, @mx);
        ");
    }
}
