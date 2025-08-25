<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategoriesTableSeeder extends Seeder
{
    public function run()
    {
        DB::unprepared(<<<'SQL'
DELETE FROM [dbo].[categories];
DBCC CHECKIDENT ('dbo.categories', RESEED, 0);

SET IDENTITY_INSERT [dbo].[categories] ON;

INSERT INTO [dbo].[categories] (id, name, description, created_at, updated_at) VALUES
(1, 'Travel',  'Travel ideas for everyone',                          GETDATE(), GETDATE()),
(2, 'Food',    'Our favourite recipes',                              GETDATE(), GETDATE()),
(3, 'Home',    'The latest trends in home decorations',              GETDATE(), GETDATE()),
(4, 'Fashion', 'Stay in touch with the latest trends',               GETDATE(), GETDATE()),
(5, 'Health',  'An apple a day keeps the doctor away',               GETDATE(), GETDATE());

SET IDENTITY_INSERT [dbo].[categories] OFF;

DECLARE @mx INT = (SELECT ISNULL(MAX([id]),0) FROM [dbo].[categories]);
DBCC CHECKIDENT ('dbo.categories', RESEED, @mx);
SQL
        );
    }
}
