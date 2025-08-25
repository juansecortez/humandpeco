<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItemsTableSeeder extends Seeder
{
    public function run()
    {
        DB::unprepared(<<<'SQL'
DELETE FROM [dbo].[items];
DBCC CHECKIDENT ('dbo.items', RESEED, 0);

SET IDENTITY_INSERT [dbo].[items] ON;

INSERT INTO [dbo].[items]
    (id, name, description, category_id, status, date, picture, show_on_homepage, options, created_at, updated_at)
VALUES
    (1, '5 citybreak ideas for this year',
     'Lorem ipsum dolor sit amet, consectetur adipiscing elit...',
     1, 'published', CONVERT(date, GETDATE()), 'pictures/img1.jpg', 1, '["0","1"]', GETDATE(), GETDATE()),
    (2, 'Top 10 restaurants in Italy',
     'Lorem ipsum dolor sit amet, consectetur adipiscing elit...',
     2, 'published', CONVERT(date, GETDATE()), 'pictures/img2.jpg', 1, '["0","1"]', GETDATE(), GETDATE()),
    (3, 'Cocktail ideas for your birthday party',
     'Lorem ipsum dolor sit amet, consectetur adipiscing elit...',
     2, 'published', CONVERT(date, GETDATE()), 'pictures/img3.jpg', 1, '["0","1"]', GETDATE(), GETDATE());

SET IDENTITY_INSERT [dbo].[items] OFF;

DECLARE @mx INT = (SELECT ISNULL(MAX([id]),0) FROM [dbo].[items]);
DBCC CHECKIDENT ('dbo.items', RESEED, @mx);
SQL
        );

        DB::table('item_tag')->insert([
            ['item_id' => 1, 'tag_id' => 1],
            ['item_id' => 1, 'tag_id' => 2],
            ['item_id' => 1, 'tag_id' => 3],
            ['item_id' => 2, 'tag_id' => 1],
            ['item_id' => 3, 'tag_id' => 1],
        ]);
    }
}
