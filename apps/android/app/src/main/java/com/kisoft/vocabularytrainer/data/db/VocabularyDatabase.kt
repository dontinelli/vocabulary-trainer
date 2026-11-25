package com.kisoft.vocabularytrainer.data.db

import androidx.room.Database
import androidx.room.RoomDatabase
import androidx.room.migration.Migration
import androidx.sqlite.db.SupportSQLiteDatabase

@Database(
    entities = [
        CollectionEntity::class,
        EntryEntity::class,
        CollectionStatisticsEntity::class,
        DashboardStatisticsEntity::class
    ],
    version = 2,
    exportSchema = false
)
abstract class VocabularyDatabase : RoomDatabase() {
    abstract fun collectionDao(): CollectionDao

    companion object {
        val MIGRATION_1_2 = object : Migration(1, 2) {
            override fun migrate(db: SupportSQLiteDatabase) {
                db.execSQL(
                    "CREATE TABLE IF NOT EXISTS `entries` (" +
                        "`id` INTEGER NOT NULL, `collectionId` INTEGER NOT NULL, " +
                        "`sourceTerm` TEXT NOT NULL, `targetTerm` TEXT NOT NULL, `notes` TEXT, " +
                        "PRIMARY KEY(`id`), FOREIGN KEY(`collectionId`) REFERENCES `collections`(`id`) " +
                        "ON UPDATE NO ACTION ON DELETE CASCADE)"
                )
                db.execSQL("CREATE INDEX IF NOT EXISTS `index_entries_collectionId` ON `entries` (`collectionId`)")
                db.execSQL(
                    "CREATE TABLE IF NOT EXISTS `collection_statistics` (" +
                        "`collectionId` INTEGER NOT NULL, `boxCount` INTEGER NOT NULL, " +
                        "`boxCountsJson` TEXT NOT NULL, `entryProgressJson` TEXT NOT NULL, " +
                        "`accuracyPercent` REAL, `learningProgressPercent` REAL, " +
                        "PRIMARY KEY(`collectionId`), FOREIGN KEY(`collectionId`) REFERENCES `collections`(`id`) " +
                        "ON UPDATE NO ACTION ON DELETE CASCADE)"
                )
                db.execSQL(
                    "CREATE TABLE IF NOT EXISTS `dashboard_statistics` (" +
                        "`id` INTEGER NOT NULL, `wordPairCount` INTEGER NOT NULL, " +
                        "`learningProgressPercent` REAL, `accuracyPercent` REAL, " +
                        "`practicedTodayCount` INTEGER NOT NULL, PRIMARY KEY(`id`))"
                )
            }
        }
    }
}
