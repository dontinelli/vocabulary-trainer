package com.kisoft.vocabularytrainer.ui

import android.content.res.Resources
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.CancellationException
import com.kisoft.vocabularytrainer.R
import com.kisoft.vocabularytrainer.data.auth.LoginCredentials
import com.kisoft.vocabularytrainer.data.auth.LoginStart
import com.kisoft.vocabularytrainer.data.db.CollectionEntity
import com.kisoft.vocabularytrainer.data.db.CollectionStatisticsEntity
import com.kisoft.vocabularytrainer.data.db.DashboardStatisticsEntity
import com.kisoft.vocabularytrainer.data.db.EntryEntity
import com.kisoft.vocabularytrainer.data.model.CollectionWriteRequest
import com.kisoft.vocabularytrainer.data.model.EntryWriteRequest
import com.kisoft.vocabularytrainer.data.model.StudyAnswerDto
import com.kisoft.vocabularytrainer.data.model.StudyAnswerRequest
import com.kisoft.vocabularytrainer.data.model.StudyQuestionDto
import com.kisoft.vocabularytrainer.data.model.StudySessionDto
import com.kisoft.vocabularytrainer.data.model.StudySessionRequest
import com.kisoft.vocabularytrainer.data.model.StudySettingsDto
import com.kisoft.vocabularytrainer.data.repository.CollectionRepository
import com.kisoft.vocabularytrainer.data.repository.LoginRepository
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.flow.flatMapLatest
import kotlinx.coroutines.flow.flowOf
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

@OptIn(ExperimentalCoroutinesApi::class)
class MainViewModel(
    private val loginRepository: LoginRepository,
    private val collectionRepository: CollectionRepository,
    private val resources: Resources
) : ViewModel() {
    private var cacheRefreshJob: Job? = null
    private val _state = MutableStateFlow(MainState())
    val state: StateFlow<MainState> = _state.asStateFlow()
    val collections = collectionRepository.observeCollections()
    val dashboardStatistics = collectionRepository.observeDashboardStatistics()

    val entries: StateFlow<List<EntryEntity>> = state
        .map { it.selectedCollectionId }
        .distinctUntilChanged()
        .flatMapLatest { id ->
            if (id == null) flowOf(emptyList()) else collectionRepository.observeEntries(id)
        }
        .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5_000), emptyList())

    val collectionStatistics: StateFlow<CollectionStatisticsEntity?> = state
        .map { it.selectedCollectionId }
        .distinctUntilChanged()
        .flatMapLatest { id ->
            if (id == null) flowOf(null) else collectionRepository.observeCollectionStatistics(id)
        }
        .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5_000), null)

    init {
        val account = loginRepository.loadAccount()
        _state.value = _state.value.copy(
            account = account,
            screen = if (account == null) AppScreen.LOGIN else AppScreen.DASHBOARD
        )
        if (account != null) refreshCollections(account)
    }

    fun startLogin(serverUrl: String) {
        _state.value = _state.value.copy(serverUrl = serverUrl, busy = true, message = null)
        viewModelScope.launch {
            try {
                val start = withContext(Dispatchers.IO) {
                    loginRepository.start(serverUrl)
                }
                _state.value = _state.value.copy(busy = false, pendingLogin = start)
            } catch (error: CancellationException) {
                throw error
            } catch (error: Exception) {
                showError(R.string.login_start_error, error)
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
                        message = resources.getString(R.string.login_incomplete)
                    )
                } else {
                    _state.value = _state.value.copy(
                        account = account,
                        pendingLogin = null,
                        busy = false,
                        screen = AppScreen.DASHBOARD,
                        message = resources.getString(R.string.connected)
                    )
                    refreshCollections(account)
                }
            } catch (error: CancellationException) {
                throw error
            } catch (error: Exception) {
                showError(R.string.login_complete_error, error)
            }
        }
    }

    fun refreshCollections(account: LoginCredentials) {
        perform(R.string.refresh_error) { collectionRepository.refresh(account) }
    }

    fun openCollection(id: Int) {
        _state.value = _state.value.copy(
            selectedCollectionId = id,
            screen = AppScreen.COLLECTION,
            message = null
        )
        val account = _state.value.account ?: return
        perform(R.string.cached_collection_details) {
            collectionRepository.refreshCollectionDetails(account, id)
        }
    }

    fun openDashboard() {
        _state.value = _state.value.copy(
            screen = AppScreen.DASHBOARD,
            selectedCollectionId = null,
            studySession = null,
            studyAnswer = null,
            message = null
        )
    }

    fun openCollectionBack() {
        _state.value = _state.value.copy(screen = AppScreen.COLLECTION, message = null)
    }

    fun createCollection(request: CollectionWriteRequest) {
        val account = _state.value.account ?: return
        perform(R.string.create_collection_error) {
            collectionRepository.createCollection(account, request)
        }
    }

    fun updateCollection(id: Int, request: CollectionWriteRequest) {
        val account = _state.value.account ?: return
        perform(R.string.update_collection_error) {
            collectionRepository.updateCollection(account, id, request)
        }
    }

    fun deleteCollection(id: Int) {
        val account = _state.value.account ?: return
        perform(R.string.delete_collection_error) {
            collectionRepository.deleteCollection(account, id)
            if (_state.value.selectedCollectionId == id) openDashboard()
        }
    }

    fun createEntry(request: EntryWriteRequest) {
        val account = _state.value.account ?: return
        val collectionId = _state.value.selectedCollectionId ?: return
        perform(R.string.add_word_pair_error) {
            collectionRepository.createEntry(account, collectionId, request)
        }
    }

    fun updateEntry(entryId: Int, request: EntryWriteRequest) {
        val account = _state.value.account ?: return
        val collectionId = _state.value.selectedCollectionId ?: return
        perform(R.string.update_word_pair_error) {
            collectionRepository.updateEntry(account, collectionId, entryId, request)
        }
    }

    fun deleteEntry(entryId: Int) {
        val account = _state.value.account ?: return
        val collectionId = _state.value.selectedCollectionId ?: return
        perform(R.string.delete_word_pair_error) {
            collectionRepository.deleteEntry(account, collectionId, entryId)
        }
    }

    fun deleteEntries(entryIds: List<Int>) {
        val account = _state.value.account ?: return
        val collectionId = _state.value.selectedCollectionId ?: return
        perform(R.string.delete_selected_word_pairs_error) {
            collectionRepository.deleteEntries(account, collectionId, entryIds)
        }
    }

    fun moveEntries(targetCollectionId: Int, entryIds: List<Int>) {
        val account = _state.value.account ?: return
        val collectionId = _state.value.selectedCollectionId ?: return
        perform(R.string.move_selected_word_pairs_error) {
            collectionRepository.moveEntries(account, collectionId, targetCollectionId, entryIds)
        }
    }

    fun openStudySetup() {
        val account = _state.value.account ?: return
        val collectionId = _state.value.selectedCollectionId ?: return
        _state.value = _state.value.copy(
            screen = AppScreen.STUDY_SETUP,
            studySettings = null,
            message = null
        )
        perform(R.string.load_study_settings_error) {
            val settings = collectionRepository.getStudySettings(account, collectionId)
            _state.value = _state.value.copy(studySettings = settings)
        }
    }

    fun startStudySession(request: StudySessionRequest) {
        val account = _state.value.account ?: return
        val collectionId = _state.value.selectedCollectionId ?: return
        _state.value = _state.value.copy(busy = true, message = null)
        viewModelScope.launch {
            try {
                val session = withContext(Dispatchers.IO) {
                    collectionRepository.startStudySession(account, collectionId, request)
                }
                if (session.questions.isEmpty()) {
                    _state.value = _state.value.copy(
                        busy = false,
                        message = resources.getString(R.string.no_matching_word_pairs)
                    )
                } else {
                    _state.value = _state.value.copy(
                        busy = false,
                        screen = AppScreen.STUDY,
                        studySession = session,
                        questionIndex = 0,
                        studyAnswer = null,
                        correctAnswers = 0,
                        message = null
                    )
                }
            } catch (error: CancellationException) {
                throw error
            } catch (error: Exception) {
                showError(R.string.start_study_error, error)
            }
        }
    }

    fun submitStudyAnswer(answer: String) {
        val account = _state.value.account ?: return
        val collectionId = _state.value.selectedCollectionId ?: return
        val question = currentQuestion() ?: return
        if (_state.value.studyAnswer != null || _state.value.busy) return
        val direction = _state.value.studySession?.direction ?: return
        _state.value = _state.value.copy(busy = true, message = null)
        viewModelScope.launch {
            try {
                val result = withContext(Dispatchers.IO) {
                    collectionRepository.submitAnswer(
                        account,
                        collectionId,
                        question.entryId,
                        StudyAnswerRequest(direction, answer)
                    )
                }
                _state.value = _state.value.copy(
                    busy = false,
                    studyAnswer = result,
                    correctAnswers = _state.value.correctAnswers + if (result.correct) 1 else 0
                )
                refreshAfterAnswer(account, collectionId)
            } catch (error: CancellationException) {
                throw error
            } catch (error: Exception) {
                showError(R.string.submit_answer_error, error)
            }
        }
    }

    fun advanceStudy() {
        val session = _state.value.studySession ?: return
        if (_state.value.questionIndex + 1 >= session.questions.size) {
            _state.value = _state.value.copy(screen = AppScreen.STUDY_SUMMARY)
        } else {
            _state.value = _state.value.copy(
                questionIndex = _state.value.questionIndex + 1,
                studyAnswer = null,
                message = null
            )
        }
    }

    fun finishStudy() {
        openCollection(_state.value.selectedCollectionId ?: return)
    }

    fun currentQuestion(): StudyQuestionDto? {
        val session = _state.value.studySession ?: return null
        return session.questions.getOrNull(_state.value.questionIndex)
    }

    fun signOut() {
        if (_state.value.busy) return
        cacheRefreshJob?.cancel()
        loginRepository.clearAccount()
        val serverUrl = _state.value.serverUrl
        _state.value = MainState(serverUrl = serverUrl, busy = true)
        viewModelScope.launch {
            try {
                withContext(Dispatchers.IO) { collectionRepository.clear() }
                _state.value = MainState(serverUrl = serverUrl)
            } catch (error: CancellationException) {
                throw error
            } catch (error: Exception) {
                _state.value = MainState(
                    serverUrl = serverUrl,
                    message = resources.getString(
                        R.string.clear_account_error,
                        error.message ?: error.javaClass.simpleName
                    )
                )
            }
        }
    }

    private fun refreshAfterAnswer(account: LoginCredentials, collectionId: Int) {
        cacheRefreshJob = viewModelScope.launch {
            try {
                withContext(Dispatchers.IO) {
                    collectionRepository.refreshCollectionDetails(account, collectionId)
                    collectionRepository.refreshCollections(account)
                    collectionRepository.refreshDashboardStatistics(account)
                }
            } catch (error: Exception) {
                if (error is CancellationException) throw error
                _state.value = _state.value.copy(
                    message = resources.getString(
                        R.string.answer_saved_refresh_error,
                        error.message ?: error.javaClass.simpleName
                    )
                )
            }
        }
    }

    private fun perform(
        errorPrefix: Int,
        action: suspend () -> Unit
    ) {
        if (_state.value.busy) return
        _state.value = _state.value.copy(busy = true, message = null)
        viewModelScope.launch {
            try {
                withContext(Dispatchers.IO) { action() }
                _state.value = _state.value.copy(busy = false, message = null)
            } catch (error: Exception) {
                if (error is CancellationException) throw error
                _state.value = _state.value.copy(
                    busy = false,
                    message = "${resources.getString(errorPrefix)}: ${error.message ?: error.javaClass.simpleName}"
                )
            }
        }
    }

    private fun showError(prefix: Int, error: Exception) {
        _state.value = _state.value.copy(
            busy = false,
            message = "${resources.getString(prefix)}: ${error.message ?: error.javaClass.simpleName}"
        )
    }

    class Factory(
        private val loginRepository: LoginRepository,
        private val collectionRepository: CollectionRepository,
        private val resources: Resources
    ) : ViewModelProvider.Factory {
        @Suppress("UNCHECKED_CAST")
        override fun <T : ViewModel> create(modelClass: Class<T>): T =
            MainViewModel(loginRepository, collectionRepository, resources) as T
    }
}

enum class AppScreen {
    LOGIN,
    DASHBOARD,
    COLLECTION,
    STUDY_SETUP,
    STUDY,
    STUDY_SUMMARY
}

data class MainState(
    val serverUrl: String = "",
    val account: LoginCredentials? = null,
    val pendingLogin: LoginStart? = null,
    val busy: Boolean = false,
    val message: String? = null,
    val screen: AppScreen = AppScreen.LOGIN,
    val selectedCollectionId: Int? = null,
    val studySettings: StudySettingsDto? = null,
    val studySession: StudySessionDto? = null,
    val questionIndex: Int = 0,
    val studyAnswer: StudyAnswerDto? = null,
    val correctAnswers: Int = 0
)
