package com.kisoft.vocabularytrainer.data.repository

import com.kisoft.vocabularytrainer.data.auth.AccountStore
import com.kisoft.vocabularytrainer.data.auth.LoginCredentials
import com.kisoft.vocabularytrainer.data.auth.LoginFlowApi
import com.kisoft.vocabularytrainer.data.auth.LoginPollResponse
import com.kisoft.vocabularytrainer.data.auth.LoginStart
import com.kisoft.vocabularytrainer.data.auth.LoginStartResponse
import java.net.URI
import java.net.URISyntaxException
import java.util.concurrent.TimeUnit
import okhttp3.OkHttpClient
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory

class LoginRepository(
    private val accountStore: AccountStore
) {
    suspend fun start(serverUrl: String): LoginStart {
        val server = normalizeServer(serverUrl)
        val response = createApi(server).start()
        if (!response.isSuccessful) {
            throw java.io.IOException("Nextcloud returned HTTP ${response.code()} during sign-in.")
        }
        val login = response.body()
            ?: throw java.io.IOException("Nextcloud returned an invalid Login Flow response.")
        requireSameOrigin(server, login.poll.endpoint)
        requireSameOrigin(server, login.login)
        return LoginStart(
            pollToken = login.poll.token,
            pollEndpoint = login.poll.endpoint,
            loginUrl = login.login
        )
    }

    suspend fun complete(serverUrl: String, pollToken: String, pollEndpoint: String): LoginCredentials? {
        val server = normalizeServer(serverUrl)
        requireSameOrigin(server, pollEndpoint)
        val response = createApi(server).poll(pollEndpoint, mapOf("token" to pollToken))
        if (response.code() == 404) return null
        if (!response.isSuccessful) {
            throw java.io.IOException("Nextcloud returned HTTP ${response.code()} during sign-in.")
        }
        val login = response.body()
            ?: throw java.io.IOException("Nextcloud returned an invalid Login Flow response.")
        val credentials = LoginCredentials(
            server = normalizeServer(login.server),
            loginName = login.loginName,
            appPassword = login.appPassword
        )
        accountStore.save(credentials)
        return credentials
    }

    fun loadAccount(): LoginCredentials? = accountStore.load()

    fun clearAccount() = accountStore.clear()

    private fun createApi(serverUrl: String): LoginFlowApi {
        val client = OkHttpClient.Builder()
            .connectTimeout(30, TimeUnit.SECONDS)
            .readTimeout(30, TimeUnit.SECONDS)
            .writeTimeout(30, TimeUnit.SECONDS)
            .build()
        return Retrofit.Builder()
            .baseUrl("${normalizeServer(serverUrl)}/")
            .client(client)
            .addConverterFactory(GsonConverterFactory.create())
            .build()
            .create(LoginFlowApi::class.java)
    }

    private fun normalizeServer(serverUrl: String): String {
        val trimmed = serverUrl.trim().trimEnd('/')
        val uri = try {
            URI(trimmed)
        } catch (error: URISyntaxException) {
            throw IllegalArgumentException("Enter a valid Nextcloud server URL.", error)
        }
        require(uri.scheme == "http" || uri.scheme == "https") {
            "Server URL must start with http:// or https://."
        }
        require(!uri.host.isNullOrBlank() && uri.userInfo == null && uri.query == null && uri.fragment == null) {
            "Enter a valid Nextcloud server URL without credentials or query parameters."
        }
        return uri.toASCIIString()
    }

    private fun requireSameOrigin(server: String, endpoint: String) {
        val serverUri = URI(server)
        val endpointUri = try {
            URI(endpoint)
        } catch (error: URISyntaxException) {
            throw IllegalArgumentException("Nextcloud returned an invalid Login Flow URL.", error)
        }
        val sameOrigin = serverUri.scheme.equals(endpointUri.scheme, ignoreCase = true) &&
            serverUri.host.equals(endpointUri.host, ignoreCase = true) &&
            effectivePort(serverUri) == effectivePort(endpointUri)
        require(sameOrigin && endpointUri.userInfo == null) {
            "Nextcloud returned a Login Flow URL on a different server."
        }
    }

    private fun effectivePort(uri: URI): Int = when {
        uri.port >= 0 -> uri.port
        uri.scheme.equals("https", ignoreCase = true) -> 443
        else -> 80
    }
}
