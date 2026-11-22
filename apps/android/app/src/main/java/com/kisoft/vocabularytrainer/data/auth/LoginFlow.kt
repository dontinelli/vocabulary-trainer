package com.kisoft.vocabularytrainer.data.auth

data class LoginStart(
    val pollToken: String,
    val pollEndpoint: String,
    val loginUrl: String
)

data class LoginStartResponse(
    val poll: PollInfo,
    val login: String
) {
    data class PollInfo(
        val token: String,
        val endpoint: String
    )
}

data class LoginCredentials(
    val server: String,
    val loginName: String,
    val appPassword: String
)

data class LoginPollResponse(
    val server: String,
    val loginName: String,
    val appPassword: String
)
