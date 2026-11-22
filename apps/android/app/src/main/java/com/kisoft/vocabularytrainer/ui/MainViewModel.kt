package com.kisoft.vocabularytrainer.ui

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.kisoft.vocabularytrainer.data.auth.LoginCredentials
import com.kisoft.vocabularytrainer.data.auth.LoginStart
import com.kisoft.vocabularytrainer.data.db.CollectionEntity
import com.kisoft.vocabularytrainer.data.repository.CollectionRepository
import com.kisoft.vocabularytrainer.data.repository.LoginRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
class MainViewModel(
    private val loginRepository: LoginRepository,
    private val collectionRepository: CollectionRepository
) : ViewModel() {
    private val _state = MutableStateFlow(MainState())
    val state: StateFlow<MainState> = _state.asStateFlow()

    init {
        val account = loginRepository.loadAccount()
        _state.value = _state.value.copy(account = account)
        if (account != null) {
            refreshCollections(account)
        }
    }

    val collections = collectionRepository.observeCollections()

    fun startLogin(serverUrl: String) {
        _state.value = _state.value.copy(serverUrl = serverUrl, busy = true, message = null)
        viewModelScope.launch {
            try {
                val start = withContext(Dispatchers.IO) {
                    loginRepository.start(serverUrl)
                }
                _state.value = _state.value.copy(
                    busy = false,
                    pendingLogin = start
                )
            } catch (error: Exception) {
                _state.value = _state.value.copy(
                    busy = false,
                    message = "Could not start Nextcloud sign-in: ${error.message ?: error.javaClass.simpleName}"
                )
            }
        }
    }

    fun completeLogin() {
        val start = _state.value.pendingLogin ?: return
        val serverUrl = _state.value.serverUrl
        _state.value = _state.value.copy(busy = true, message = null)
        viewModelScope.launch {
            try {
                val account = withContext(Dispatchers.IO) {
                    loginRepository.complete(serverUrl, start.pollToken, start.pollEndpoint)
                }
                if (account == null) {
                    _state.value = _state.value.copy(
                        busy = false,
                        message = "Sign-in is not complete yet."
                    )
                } else {
                    _state.value = _state.value.copy(
                        account = account,
                        pendingLogin = null,
                        busy = false,
                        message = "Connected."
                    )
                    refreshCollections(account)
                }
            } catch (error: Exception) {
                _state.value = _state.value.copy(
                    busy = false,
                    message = "Could not complete Nextcloud sign-in: ${error.message ?: error.javaClass.simpleName}"
                )
            }
        }
    }

    fun refreshCollections(account: LoginCredentials) {
        _state.value = _state.value.copy(busy = true)
        viewModelScope.launch {
            try {
                withContext(Dispatchers.IO) {
                    collectionRepository.refresh(account)
                }
                _state.value = _state.value.copy(busy = false, message = null)
            } catch (error: Exception) {
                _state.value = _state.value.copy(
                    busy = false,
                    message = "Cached collections shown. ${error.message ?: "Refresh failed."}"
                )
            }
        }
    }

    fun signOut() {
        loginRepository.clearAccount()
        val serverUrl = _state.value.serverUrl
        _state.value = MainState(serverUrl = serverUrl, busy = true)
        viewModelScope.launch {
            withContext(Dispatchers.IO) {
                collectionRepository.clear()
            }
            _state.value = MainState(serverUrl = serverUrl)
        }
    }

    class Factory(
        private val loginRepository: LoginRepository,
        private val collectionRepository: CollectionRepository
    ) : ViewModelProvider.Factory {
        @Suppress("UNCHECKED_CAST")
        override fun <T : ViewModel> create(modelClass: Class<T>): T =
            MainViewModel(loginRepository, collectionRepository) as T
    }
}

data class MainState(
    val serverUrl: String = "",
    val account: LoginCredentials? = null,
    val pendingLogin: LoginStart? = null,
    val busy: Boolean = false,
    val message: String? = null
)
