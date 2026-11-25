package com.kisoft.vocabularytrainer.data.db

import androidx.room.Entity
import androidx.room.ForeignKey
import androidx.room.PrimaryKey

@Entity(
    tableName = "collection_statistics",
    foreignKeys = [
        ForeignKey(
            entity = CollectionEntity::class,
            parentColumns = ["id"],
            childColumns = ["collectionId"],
            onDelete = ForeignKey.CASCADE
        )
    ]
)
data class CollectionStatisticsEntity(
    @PrimaryKey val collectionId: Int,
    val boxCount: Int,
    val boxCountsJson: String,
    val entryProgressJson: String,
    val accuracyPercent: Double?,
    val learningProgressPercent: Double?
)
