package com.kisoft.vocabularytrainer.data.db

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import androidx.room.Transaction
import kotlinx.coroutines.flow.Flow

@Dao
interface CollectionDao {
    @Query("SELECT * FROM collections ORDER BY name")
    fun observeAll(): Flow<List<CollectionEntity>>

    @Query("DELETE FROM collections")
    suspend fun clear()

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun replaceAll(collections: List<CollectionEntity>)

    @Transaction
    suspend fun replaceCollectionList(collections: List<CollectionEntity>) {
        clear()
        replaceAll(collections)
    }
}
