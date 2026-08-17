<?php

declare(strict_types=1);

use MediaPitch\Core\Database;

require dirname(__DIR__) . '/src/bootstrap.php';

$db = Database::connection();
$db->beginTransaction();

try {
    // Administrator credentials are intentionally not seeded here. The
    // deployment's bootstrap-admin.php step owns administrator creation and
    // recovery from BOOTSTRAP_ADMIN_* environment variables. Keeping that in
    // one place prevents a hardcoded seed password from fighting production
    // credentials during deploys.
    $userCount = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($userCount === 0) {
        fwrite(STDOUT, "No users exist yet; bootstrap-admin.php will create the configured administrator when BOOTSTRAP_ADMIN_PASSWORD is set.\n");
    } else {
        fwrite(STDOUT, "Existing users preserved; administrator credentials are managed separately by bootstrap-admin.php.\n");
    }

    $category = $db->prepare(
        "INSERT INTO categories (name, slug, description, sort_order, active)
         VALUES ('Books', 'books', 'Islamic books, children\'s books and educational reading from MediaPitch and Fill Masjid.', 10, 1)
         ON DUPLICATE KEY UPDATE id=id"
    );
    $category->execute();
    $categoryId = (int) $db->query("SELECT id FROM categories WHERE slug='books' LIMIT 1")->fetchColumn();

    $brand = $db->prepare(
        "INSERT INTO brands (name, slug, website_url)
         VALUES ('Media Pitch', 'media-pitch', 'https://mediapitch.in')
         ON DUPLICATE KEY UPDATE id=id"
    );
    $brand->execute();
    $brandId = (int) $db->query("SELECT id FROM brands WHERE slug='media-pitch' LIMIT 1")->fetchColumn();

    $products = [
        [
            'title' => 'Know Your Prophets: Islamic Bedtime Stories for Kids (Ages 5–9)',
            'slug' => 'know-your-prophets',
            'short_description' => 'A gentle collection of short, child-friendly stories introducing young readers to the 25 prophets mentioned in the Holy Quran.',
            'full_description' => '<p><strong>Know Your Prophets</strong> is designed for children aged 5–9, with short bedtime-sized chapters, simple language and lessons drawn from the lives of the prophets.</p><p>The book is compiled by the Fill Masjid App Team and published by Media Pitch. It is intended to help children build reading confidence while learning about faith, patience, kindness, honesty, courage and trust in Allah.</p>',
            'features' => ['Paperback', 'English', 'Ages 5–9', 'Stories of 25 prophets mentioned in the Holy Quran', 'Published in 2024', 'ISBN 978-81-974782-7-7'],
            'price' => 363.00,
            'best_for' => 'Bedtime reading for ages 5–9',
            'score' => 9.2,
            'notes' => 'Default seed product. Core bibliographic details verified from current retail/catalog listings and the original Media Pitch edition.',
        ],
        [
            'title' => 'The Path of the Caliphs: Islamic Stories of the Khulafa-e-Rashideen for Children',
            'slug' => 'the-path-of-the-caliphs',
            'short_description' => 'Islamic stories for children about the rightly guided caliphs, written to make early Islamic history approachable for younger readers.',
            'full_description' => '<p><strong>The Path of the Caliphs</strong> introduces children to the Khulafa-e-Rashideen through accessible Islamic stories. It is aimed at young readers and families looking for an age-appropriate introduction to the lives, character and leadership of the rightly guided caliphs.</p>',
            'features' => ['Children\'s Islamic history', 'Recommended for ages 5–12', 'Stories about the Khulafa-e-Rashideen'],
            'price' => null,
            'best_for' => 'Islamic history for ages 5–12',
            'score' => 9.0,
            'notes' => 'Default seed product. Title and age positioning verified from Fill Masjid references and current book listings. Price/ISBN intentionally left unset until verified.',
        ],
        [
            'title' => 'Growing with Adab: An Islamic Guide for Muslim Teenagers',
            'slug' => 'growing-with-adab',
            'short_description' => 'A practical Islamic guide for Muslim teenagers focused on faith, identity, character, hygiene and navigating modern life with adab.',
            'full_description' => '<p><strong>Growing with Adab</strong> is a guide for Muslim teenagers dealing with faith, identity, character, hygiene and modern life. It is intended as a practical companion for young Muslims and families navigating the teenage years.</p>',
            'features' => ['For Muslim teenagers', 'Faith and identity', 'Character and adab', 'Hygiene and modern life'],
            'price' => null,
            'best_for' => 'Muslim teenagers and families',
            'score' => 9.0,
            'notes' => 'Default seed product. Title/theme verified from current Fill Masjid references and retail listings. Price/ISBN intentionally left unset until verified.',
        ],
    ];

    $stmt = $db->prepare(
        'INSERT INTO products
         (category_id,brand_id,title,display_title,slug,source,short_description,full_description,features_json,price,currency,custom_score,best_for_label,editorial_notes,active)
         VALUES
         (:category_id,:brand_id,:title,:display_title,:slug,\'manual\',:short_description,:full_description,:features_json,:price,\'INR\',:custom_score,:best_for_label,:editorial_notes,1)
         ON DUPLICATE KEY UPDATE id=id'
    );

    foreach ($products as $product) {
        $stmt->execute([
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'title' => $product['title'],
            'display_title' => $product['title'],
            'slug' => $product['slug'],
            'short_description' => $product['short_description'],
            'full_description' => $product['full_description'],
            'features_json' => json_encode($product['features'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'price' => $product['price'],
            'custom_score' => $product['score'],
            'best_for_label' => $product['best_for'],
            'editorial_notes' => $product['notes'],
        ]);
    }

    $db->commit();
    fwrite(STDOUT, "Default MediaPitch data checked/seeded successfully. Existing CMS edits were preserved.\n");
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'Seed failed: ' . $e->getMessage() . "\n");
    exit(1);
}
