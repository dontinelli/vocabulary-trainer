package com.kisoft.vocabularytrainer.data.db

import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "dashboard_statistics")
data class DashboardStatisticsEntity(
    @PrimaryKey val id: Int = 1,
    val wordPairCount: Int,
    val learningProgressPercent: Double?,
    val accuracyPercent: Double?,
    val practicedTodayCount: Int
)
