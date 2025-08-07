<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\CategoryMapping;
use App\Services\ZohoApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ImportArticlesFromJson extends Command
{
    protected $signature = 'import:articles {filePath}';
    protected $description = 'Import all articles from a JSON file and log them in the articles table';

    public function handle()
    {
        DB::table('articles')->truncate();

        $filePath = $this->argument('filePath');

        if (!File::exists($filePath)) {
            $this->error("File not found at path: $filePath");
            return;
        }

        $jsonData = json_decode(File::get($filePath), true);

        if (!is_array($jsonData)) {
            $this->error("Invalid JSON structure");
            return;
        }

        $importedCount = 0;

        $categoryMappings = CategoryMapping::all();
        $zohoService = new ZohoApiService();

        foreach ($jsonData as $categoryItem) {

            $categoryId = $categoryItem['category']['id'] ?? null;

            if(!$categoryId) continue;

            if($categoryId != '154000296993'){
                continue;
            }

            foreach ($categoryItem['category']['all_folders'] ?? [] as $folder) {

                foreach ($folder['articles'] ?? [] as $article) {

                    $article_id = $article['id'];
                    $article_in_db = Article::where('article_id', $article_id)->first();

                    if($article_in_db) continue;

                    try {

                        if ($categoryId == '154000296993') {
                            $category = $categoryMappings->where('old_category_id', (int) $article_id)->first();
                        }
                        else {
                            $category = $categoryMappings->where('old_category_id', $categoryId)->first();
                        }

                        $zoho_category_id = $category->zoho_category_id;

                        $internal_id = DB::table('articles')->updateOrInsert(
                            ['article_id' => $article_id],
                            [
                                'title' => $article['title'],
                                'category_id' => $zoho_category_id,
                                'status' => $article['status'] ?? null,
                                'created_at_api' => isset($article['created_at']) ? Carbon::parse($article['created_at']) : null,
                                'updated_at_api' => isset($article['updated_at']) ? Carbon::parse($article['updated_at']) : null,
                                'updated_at' => now(),
                                'created_at' => now(),
                            ]
                        );

                        $payload = [
                            'title' => $article['title'],
                            'authorId' => '1120359000000139001',
                            'answer' => $article['description'],
                            'categoryId' => sprintf("%s", $zoho_category_id),
                            'status' => 'Published',
                            'permission' => 'ALL',
                            'permalink' => Str::slug($article['title']),
                        ];

//                        dump($payload);

                        $zohoService->createRecord('articles', $payload, $internal_id);

                        $this->info("Imported article: {$article['title']}: {$category->category_name}");
                        $importedCount++;


                    } catch (\Exception $e) {
                        dd($e->getMessage(), $e->getLine() );
                        $this->error("Failed to import article ID {$article['id']}: " . $e->getMessage());
                    }
                }
            }
        }

        $this->info("✅ Imported $importedCount articles successfully.");
    }
}
