<?php
declare(strict_types=1);

$directory = __DIR__ . '/../l10n/';
$referenceFile = $directory . 'de.json';
$referenceContents = file_get_contents($referenceFile);
if ($referenceContents === false) {
    throw new RuntimeException('Could not read the German translation catalog.');
}

$referenceData = json_decode($referenceContents, true, 512, JSON_THROW_ON_ERROR);
$reference = $referenceData['translations'];
$expectedKeys = array_keys($reference);
sort($expectedKeys);

foreach (['de', 'fr', 'it'] as $locale) {
    $file = $directory . $locale . '.json';
    $contents = file_get_contents($file);
    if ($contents === false) {
        throw new RuntimeException("Could not read the $locale translation catalog.");
    }

    $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    $translations = $data['translations'];
    if (($translations['']['language'] ?? null) !== $locale) {
        throw new RuntimeException("The $locale catalog has incorrect language metadata.");
    }

    $actualKeys = array_keys($translations);
    sort($actualKeys);
    if ($actualKeys !== $expectedKeys) {
        throw new RuntimeException("The $locale catalog does not have the same keys as de.json.");
    }

    foreach ($reference as $key => $_value) {
        if ($key === '') {
            continue;
        }

        $value = $translations[$key];
        if (!is_string($value)) {
            throw new RuntimeException("The $locale translation for \"$key\" is not a string.");
        }

        preg_match_all('/\{[^{}]+\}/', $key, $sourcePlaceholders);
        preg_match_all('/\{[^{}]+\}/', $value, $translatedPlaceholders);
        sort($sourcePlaceholders[0]);
        sort($translatedPlaceholders[0]);
        if ($sourcePlaceholders[0] !== $translatedPlaceholders[0]) {
            throw new RuntimeException("The $locale translation for \"$key\" has different placeholders.");
        }
    }
}

echo "Translation catalogs are valid and complete.\n";
