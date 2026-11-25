package com.kisoft.vocabularytrainer.data.auth

import retrofit2.http.POST
import retrofit2.http.Url
import retrofit2.Response

interface LoginFlowApi {
    @POST("index.php/login/v2")
    suspend fun start(): Response<LoginStartResponse>

    @POST
    suspend fun poll(
        @Url endpoint: String,
        @retrofit2.http.Body body: Map<String, String>
    ): Response<LoginPollResponse>
}
