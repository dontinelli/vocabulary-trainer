package com.kisoft.vocabularytrainer.data.auth

import android.content.Context
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey
class AccountStore(context: Context) {
    private val masterKey = MasterKey.Builder(context)
        .setKeyScheme(MasterKey.KeyScheme.AES256_GCM)
        .build()

    private val preferences = EncryptedSharedPreferences.create(
        context,
        "vocabulary_trainer_account",
        masterKey,
        EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
        EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
    )

    fun load(): LoginCredentials? {
        val server = preferences.getString("server", null) ?: return null
        val loginName = preferences.getString("loginName", null) ?: return null
        val appPassword = preferences.getString("appPassword", null) ?: return null
        return LoginCredentials(server, loginName, appPassword)
    }

    fun save(credentials: LoginCredentials) {
        preferences.edit()
            .putString("server", credentials.server)
            .putString("loginName", credentials.loginName)
            .putString("appPassword", credentials.appPassword)
            .apply()
    }

    fun clear() {
        preferences.edit().clear().apply()
    }
}
