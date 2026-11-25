package com.kisoft.vocabularytrainer.data.model

data class CollectionDto(
    val id: Int,
    val name: String,
    val sourceLanguage: String,
    val targetLanguage: String,
    val boxCount: Int,
    val entryCount: Int,
    val learningProgressPercent: Double?
)
