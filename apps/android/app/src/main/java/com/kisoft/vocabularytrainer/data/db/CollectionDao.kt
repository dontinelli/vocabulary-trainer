package com.kisoft.vocabularytrainer.data.db

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import androidx.room.Transaction
import androidx.room.Upsert
import kotlinx.coroutines.flow.Flow

@Dao
interface CollectionDao {
    @Query("SELECT * FROM collections ORDER BY name")
    fun observeAll(): Flow<List<CollectionEntity>>

    @Query("DELETE FROM collections")
    suspend fun clear()

    @Query("DELETE FROM dashboard_statistics")
    suspend fun clearDashboardStatistics()

    @Query("DELETE FROM entries WHERE collectionId = :collectionId")
    suspend fun clearEntries(collectionId: Int)

    @Upsert
    suspend fun upsertCollections(collections: List<CollectionEntity>)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertEntries(entries: List<EntryEntity>)

    @Upsert
    suspend fun upsertCollectionStatistics(statistics: CollectionStatisticsEntity)

    @Upsert
    suspend fun upsertDashboardStatistics(statistics: DashboardStatisticsEntity)

    @Query("DELETE FROM collections WHERE id NOT IN (:ids)")
    suspend fun deleteCollectionsNotIn(ids: List<Int>)

    @Query("SELECT * FROM entries WHERE collectionId = :collectionId ORDER BY sourceTerm")
    fun observeEntries(collectionId: Int): Flow<List<EntryEntity>>

    @Query("SELECT * FROM collection_statistics WHERE collectionId = :collectionId")
    fun observeCollectionStatistics(collectionId: Int): Flow<CollectionStatisticsEntity?>

    @Query("SELECT * FROM dashboard_statistics WHERE id = 1")
    fun observeDashboardStatistics(): Flow<DashboardStatisticsEntity?>

    @Transaction
    suspend fun replaceCollectionList(collections: List<CollectionEntity>) {
        if (collections.isEmpty()) {
            clear()
        } else {
            upsertCollections(collections)
            deleteCollectionsNotIn(collections.map { it.id })
        }
    }

    @Transaction
    suspend fun replaceEntries(collectionId: Int, entries: List<EntryEntity>) {
        clearEntries(collectionId)
        insertEntries(entries)
    }

    @Transaction
    suspend fun clearCache() {
        clear()
        clearDashboardStatistics()
    }
}
