<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    public function run()
    {
        // Detectar esquema real de la tabla users
        $schema = DB::scalar("
            SELECT s.name
            FROM sys.tables t
            JOIN sys.schemas s ON s.schema_id = t.schema_id
            WHERE t.name = 'users'
        ") ?? 'dbo';

        // Generar password hasheado para el insert
        $passHash = Hash::make('secret');

        // Ejecutar todo en un solo batch
        DB::unprepared("
            DELETE FROM [{$schema}].[users];
            DBCC CHECKIDENT ('{$schema}.users', RESEED, 0);

            SET IDENTITY_INSERT [{$schema}].[users] ON;

            INSERT INTO [{$schema}].[users]
                (id, name, email, email_verified_at, password, role_id, picture, remember_token, created_at, updated_at)
            VALUES
                (1, 'Admin',   'admin@material.com',   GETDATE(), '{$passHash}', 1, '../material/img/faces/avatar.jpg', NULL, GETDATE(), GETDATE()),
                (2, 'Creator', 'creator@material.com', GETDATE(), '{$passHash}', 2, '../material/img/faces/marc.jpg',   NULL, GETDATE(), GETDATE()),
                (3, 'Member',  'member@material.com',  GETDATE(), '{$passHash}', 3, '../material/img/faces/card-profile1-square.jpg', NULL, GETDATE(), GETDATE());

            SET IDENTITY_INSERT [{$schema}].[users] OFF;

            DECLARE @mx INT = (SELECT ISNULL(MAX([id]),0) FROM [{$schema}].[users]);
            DBCC CHECKIDENT ('{$schema}.users', RESEED, @mx);
        ");
    }
}
