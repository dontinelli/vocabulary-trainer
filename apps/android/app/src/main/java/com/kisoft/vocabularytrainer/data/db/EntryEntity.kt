package com.kisoft.vocabularytrainer.data.db

import androidx.room.Entity
import androidx.room.ForeignKey
import androidx.room.Index
import androidx.room.PrimaryKey

@Entity(
    tableName = "entries",
    foreignKeys = [
        ForeignKey(
            entity = CollectionEntity::class,
            parentColumns = ["id"],
            childColumns = ["collectionId"],
            onDelete = ForeignKey.CASCADE
        )
    ],
    indices = [Index("collectionId")]
)
data class EntryEntity(
    @PrimaryKey val id: Int,
    val collectionId: Int,
    val sourceTerm: String,
    val targetTerm: String,
    val notes: String?
)
