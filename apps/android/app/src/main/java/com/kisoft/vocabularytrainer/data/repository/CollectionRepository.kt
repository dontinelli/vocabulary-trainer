package com.kisoft.vocabularytrainer.data.repository

import android.content.res.Resources
import com.google.gson.Gson
import com.kisoft.vocabularytrainer.R
import com.kisoft.vocabularytrainer.data.api.CollectionApi
import com.kisoft.vocabularytrainer.data.auth.LoginCredentials
import com.kisoft.vocabularytrainer.data.db.CollectionDao
import com.kisoft.vocabularytrainer.data.db.CollectionEntity
import com.kisoft.vocabularytrainer.data.db.CollectionStatisticsEntity
import com.kisoft.vocabularytrainer.data.db.DashboardStatisticsEntity
import com.kisoft.vocabularytrainer.data.db.EntryEntity
import com.kisoft.vocabularytrainer.data.model.BulkEntriesRequest
import com.kisoft.vocabularytrainer.data.model.CollectionWriteRequest
import com.kisoft.vocabularytrainer.data.model.EntryDto
import com.kisoft.vocabularytrainer.data.model.EntryWriteRequest
import com.kisoft.vocabularytrainer.data.model.StudyAnswerRequest
import com.kisoft.vocabularytrainer.data.model.StudySessionRequest
import okhttp3.Credentials
import okhttp3.OkHttpClient
import retrofit2.Response
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.util.concurrent.TimeUnit

class CollectionRepository(
    private val collectionDao: CollectionDao,
    private val resources: Resources
) {
    private val gson = Gson()

    fun observeCollections() = collectionDao.observeAll()
    fun observeEntries(collectionId: Int) = collectionDao.observeEntries(collectionId)
    fun observeCollectionStatistics(collectionId: Int) =
        collectionDao.observeCollectionStatistics(collectionId)
    fun observeDashboardStatistics() = collectionDao.observeDashboardStatistics()

    suspend fun clear() = collectionDao.clearCache()

    suspend fun refresh(account: LoginCredentials) {
        refreshCollections(account)
        refreshDashboardStatistics(account)
    }

    suspend fun refreshCollections(account: LoginCredentials) {
        val collections = api(account).getCollections().map {
            CollectionEntity(
                id = it.id,
                name = it.name,
                sourceLanguage = it.sourceLanguage,
                targetLanguage = it.targetLanguage,
                boxCount = it.boxCount,
                entryCount = it.entryCount,
                learningProgressPercent = it.learningProgressPercent
            )
        }
        collectionDao.replaceCollectionList(collections)
    }

    suspend fun refreshDashboardStatistics(account: LoginCredentials) {
        val statistics = api(account).getDashboardStatistics()
        collectionDao.upsertDashboardStatistics(
            DashboardStatisticsEntity(
                wordPairCount = statistics.wordPairCount,
                learningProgressPercent = statistics.learningProgressPercent,
                accuracyPercent = statistics.accuracyPercent,
                practicedTodayCount = statistics.practicedTodayCount
            )
        )
    }

    suspend fun refreshCollectionDetails(account: LoginCredentials, collectionId: Int) {
        val api = api(account)
        val entries = api.getEntries(collectionId).map { it.toEntity(collectionId) }
        val statistics = api.getCollectionStatistics(collectionId)
        collectionDao.replaceEntries(collectionId, entries)
        collectionDao.upsertCollectionStatistics(
            CollectionStatisticsEntity(
                collectionId = collectionId,
                boxCount = statistics.boxCount,
                boxCountsJson = gson.toJson(statistics.boxCounts),
                entryProgressJson = gson.toJson(statistics.entryProgress),
                accuracyPercent = statistics.accuracyPercent,
                learningProgressPercent = statistics.learningProgressPercent
            )
        )
    }

    suspend fun createCollection(account: LoginCredentials, request: CollectionWriteRequest) {
        api(account).createCollection(request).requireSuccess()
        refresh(account)
    }

    suspend fun updateCollection(
        account: LoginCredentials,
        id: Int,
        request: CollectionWriteRequest
    ) {
        api(account).updateCollection(id, request).requireSuccess()
        refresh(account)
    }

    suspend fun deleteCollection(account: LoginCredentials, id: Int) {
        api(account).deleteCollection(id).requireSuccess()
        refresh(account)
    }

    suspend fun createEntry(
        account: LoginCredentials,
        collectionId: Int,
        request: EntryWriteRequest
    ) {
        api(account).createEntry(collectionId, request).requireSuccess()
        refreshCollectionContent(account, collectionId)
    }

    suspend fun updateEntry(
        account: LoginCredentials,
        collectionId: Int,
        entryId: Int,
        request: EntryWriteRequest
    ) {
        api(account).updateEntry(collectionId, entryId, request).requireSuccess()
        refreshCollectionContent(account, collectionId)
    }

    suspend fun deleteEntry(account: LoginCredentials, collectionId: Int, entryId: Int) {
        api(account).deleteEntry(collectionId, entryId).requireSuccess()
        refreshCollectionContent(account, collectionId)
    }

    suspend fun deleteEntries(account: LoginCredentials, collectionId: Int, entryIds: List<Int>) {
        api(account).deleteEntries(
            collectionId,
            BulkEntriesRequest(entryIds = entryIds)
        ).requireSuccess()
        refreshCollectionContent(account, collectionId)
    }

    suspend fun moveEntries(
        account: LoginCredentials,
        collectionId: Int,
        targetCollectionId: Int,
        entryIds: List<Int>
    ) {
        api(account).moveEntries(
            collectionId,
            BulkEntriesRequest(entryIds = entryIds, targetCollectionId = targetCollectionId)
        ).requireSuccess()
        refreshCollectionDetails(account, collectionId)
        refreshCollectionDetails(account, targetCollectionId)
        refreshCollections(account)
        refreshDashboardStatistics(account)
    }

    suspend fun getStudySettings(account: LoginCredentials, collectionId: Int) =
        api(account).getStudySettings(collectionId)

    suspend fun startStudySession(
        account: LoginCredentials,
        collectionId: Int,
        request: StudySessionRequest
    ) = api(account).startStudySession(collectionId, request).requireSuccess()

    suspend fun submitAnswer(
        account: LoginCredentials,
        collectionId: Int,
        entryId: Int,
        request: StudyAnswerRequest
    ) = api(account).submitAnswer(collectionId, entryId, request).requireSuccess()

    private suspend fun refreshCollectionContent(account: LoginCredentials, collectionId: Int) {
        refreshCollectionDetails(account, collectionId)
        refreshCollections(account)
        refreshDashboardStatistics(account)
    }

    private fun api(account: LoginCredentials): CollectionApi {
        val client = OkHttpClient.Builder()
            .connectTimeout(30, TimeUnit.SECONDS)
            .readTimeout(30, TimeUnit.SECONDS)
            .addInterceptor { chain ->
                val request = chain.request().newBuilder()
                    .header("Authorization", Credentials.basic(account.loginName, account.appPassword))
                    .header("OCS-APIRequest", "true")
                    .header("X-Vocabulary-Trainer-API", "1")
                    .build()
                chain.proceed(request)
            }
            .build()
        return Retrofit.Builder()
            .baseUrl("${account.server.trimEnd('/')}/")
            .client(client)
            .addConverterFactory(GsonConverterFactory.create())
            .build()
            .create(CollectionApi::class.java)
    }

    private fun EntryDto.toEntity(collectionId: Int) = EntryEntity(
        id = id,
        collectionId = collectionId,
        sourceTerm = sourceTerm,
        targetTerm = targetTerm,
        notes = notes
    )

    private fun <T : Any> Response<T>.requireSuccess(): T {
        if (!isSuccessful) {
            val details = errorBody()?.string()?.takeIf { it.isNotBlank() } ?: message()
            throw IllegalStateException(resources.getString(R.string.api_request_failed, code(), details))
        }
        return body() ?: throw IllegalStateException(resources.getString(R.string.empty_server_response))
    }
}
