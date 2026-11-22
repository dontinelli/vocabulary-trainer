package com.kisoft.vocabularytrainer.data.db

import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "collections")
data class CollectionEntity(
    @PrimaryKey val id: Int,
    val name: String,
    val sourceLanguage: String,
    val targetLanguage: String,
    val boxCount: Int,
    val entryCount: Int,
    val learningProgressPercent: Double?
)
