package com.kisoft.vocabularytrainer.data.repository

import com.kisoft.vocabularytrainer.data.api.CollectionApi
import com.kisoft.vocabularytrainer.data.auth.LoginCredentials
import com.kisoft.vocabularytrainer.data.db.CollectionDao
import com.kisoft.vocabularytrainer.data.db.CollectionEntity
import okhttp3.Credentials
import okhttp3.OkHttpClient
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.util.concurrent.TimeUnit

class CollectionRepository(
    private val collectionDao: CollectionDao
) {
    fun observeCollections() = collectionDao.observeAll()

    suspend fun clear() = collectionDao.clear()

    suspend fun refresh(account: LoginCredentials) {
        val client = OkHttpClient.Builder()
            .connectTimeout(30, TimeUnit.SECONDS)
            .readTimeout(30, TimeUnit.SECONDS)
            .addInterceptor { chain ->
                val request = chain.request().newBuilder()
                    .header("Authorization", Credentials.basic(account.loginName, account.appPassword))
                    .header("OCS-APIRequest", "true")
                    .build()
                chain.proceed(request)
            }
            .build()
        val api = Retrofit.Builder()
            .baseUrl("${account.server.trimEnd('/')}/")
            .client(client)
            .addConverterFactory(GsonConverterFactory.create())
            .build()
            .create(CollectionApi::class.java)

        val collections = api.getCollections().map {
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
}
