package com.kisoft.vocabularytrainer.data.api

import com.kisoft.vocabularytrainer.data.model.CollectionDto
import retrofit2.http.GET

interface CollectionApi {
    @GET("apps/vocabulary_trainer/api/v1/collections")
    suspend fun getCollections(): List<CollectionDto>
}
