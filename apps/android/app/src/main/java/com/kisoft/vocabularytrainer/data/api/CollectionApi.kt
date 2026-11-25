package com.kisoft.vocabularytrainer.data.api

import com.kisoft.vocabularytrainer.data.model.CollectionDto
import com.kisoft.vocabularytrainer.data.model.ActionResponse
import com.kisoft.vocabularytrainer.data.model.BulkEntriesRequest
import com.kisoft.vocabularytrainer.data.model.CollectionStatisticsDto
import com.kisoft.vocabularytrainer.data.model.CollectionWriteRequest
import com.kisoft.vocabularytrainer.data.model.CountResponse
import com.kisoft.vocabularytrainer.data.model.DashboardStatisticsDto
import com.kisoft.vocabularytrainer.data.model.EntryDto
import com.kisoft.vocabularytrainer.data.model.EntryWriteRequest
import com.kisoft.vocabularytrainer.data.model.StudyAnswerDto
import com.kisoft.vocabularytrainer.data.model.StudyAnswerRequest
import com.kisoft.vocabularytrainer.data.model.StudySessionDto
import com.kisoft.vocabularytrainer.data.model.StudySessionRequest
import com.kisoft.vocabularytrainer.data.model.StudySettingsDto
import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.DELETE
import retrofit2.http.GET
import retrofit2.http.HTTP
import retrofit2.http.POST
import retrofit2.http.PUT
import retrofit2.http.Path

interface CollectionApi {
    @GET("apps/vocabulary_trainer/api/v1/collections")
    suspend fun getCollections(): List<CollectionDto>

    @GET("apps/vocabulary_trainer/api/v1/collections/dashboard/statistics")
    suspend fun getDashboardStatistics(): DashboardStatisticsDto

    @POST("apps/vocabulary_trainer/api/v1/collections")
    suspend fun createCollection(@Body request: CollectionWriteRequest): Response<ActionResponse>

    @PUT("apps/vocabulary_trainer/api/v1/collections/{id}")
    suspend fun updateCollection(
        @Path("id") id: Int,
        @Body request: CollectionWriteRequest
    ): Response<ActionResponse>

    @DELETE("apps/vocabulary_trainer/api/v1/collections/{id}")
    suspend fun deleteCollection(@Path("id") id: Int): Response<ActionResponse>

    @GET("apps/vocabulary_trainer/api/v1/collections/{collectionId}/entries")
    suspend fun getEntries(@Path("collectionId") collectionId: Int): List<EntryDto>

    @GET("apps/vocabulary_trainer/api/v1/collections/{collectionId}/statistics")
    suspend fun getCollectionStatistics(
        @Path("collectionId") collectionId: Int
    ): CollectionStatisticsDto

    @POST("apps/vocabulary_trainer/api/v1/collections/{collectionId}/entries")
    suspend fun createEntry(
        @Path("collectionId") collectionId: Int,
        @Body request: EntryWriteRequest
    ): Response<ActionResponse>

    @PUT("apps/vocabulary_trainer/api/v1/collections/{collectionId}/entries/{entryId}")
    suspend fun updateEntry(
        @Path("collectionId") collectionId: Int,
        @Path("entryId") entryId: Int,
        @Body request: EntryWriteRequest
    ): Response<ActionResponse>

    @DELETE("apps/vocabulary_trainer/api/v1/collections/{collectionId}/entries/{entryId}")
    suspend fun deleteEntry(
        @Path("collectionId") collectionId: Int,
        @Path("entryId") entryId: Int
    ): Response<ActionResponse>

    @HTTP(
        method = "DELETE",
        path = "apps/vocabulary_trainer/api/v1/collections/{collectionId}/entries",
        hasBody = true
    )
    suspend fun deleteEntries(
        @Path("collectionId") collectionId: Int,
        @Body request: BulkEntriesRequest
    ): Response<CountResponse>

    @POST("apps/vocabulary_trainer/api/v1/collections/{collectionId}/entries/move")
    suspend fun moveEntries(
        @Path("collectionId") collectionId: Int,
        @Body request: BulkEntriesRequest
    ): Response<CountResponse>

    @GET("apps/vocabulary_trainer/api/v1/collections/{collectionId}/study/settings")
    suspend fun getStudySettings(@Path("collectionId") collectionId: Int): StudySettingsDto

    @POST("apps/vocabulary_trainer/api/v1/collections/{collectionId}/study/session")
    suspend fun startStudySession(
        @Path("collectionId") collectionId: Int,
        @Body request: StudySessionRequest
    ): Response<StudySessionDto>

    @POST("apps/vocabulary_trainer/api/v1/collections/{collectionId}/study/entries/{entryId}/answer")
    suspend fun submitAnswer(
        @Path("collectionId") collectionId: Int,
        @Path("entryId") entryId: Int,
        @Body request: StudyAnswerRequest
    ): Response<StudyAnswerDto>
}
