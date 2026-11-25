package com.kisoft.vocabularytrainer

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.viewModels
import androidx.browser.customtabs.CustomTabsIntent
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.FilterChip
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.tween
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalSoftwareKeyboardController
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.core.net.toUri
import androidx.room.Room
import com.google.gson.JsonParser
import com.kisoft.vocabularytrainer.data.auth.AccountStore
import com.kisoft.vocabularytrainer.data.db.CollectionEntity
import com.kisoft.vocabularytrainer.data.db.EntryEntity
import com.kisoft.vocabularytrainer.data.db.VocabularyDatabase
import com.kisoft.vocabularytrainer.data.model.CollectionWriteRequest
import com.kisoft.vocabularytrainer.data.model.EntryWriteRequest
import com.kisoft.vocabularytrainer.data.model.StudySessionRequest
import com.kisoft.vocabularytrainer.data.model.StudySettingsDto
import com.kisoft.vocabularytrainer.data.repository.CollectionRepository
import com.kisoft.vocabularytrainer.data.repository.LoginRepository
import com.kisoft.vocabularytrainer.ui.AppScreen
import com.kisoft.vocabularytrainer.ui.filterEntriesByBox
import com.kisoft.vocabularytrainer.ui.MainViewModel
import com.kisoft.vocabularytrainer.ui.theme.VocabularyTrainerTheme

class MainActivity : ComponentActivity() {
    private val viewModel: MainViewModel by viewModels {
        val database = Room.databaseBuilder(
            applicationContext,
            VocabularyDatabase::class.java,
            "vocabulary-trainer"
        ).addMigrations(VocabularyDatabase.MIGRATION_1_2).build()
        MainViewModel.Factory(
            LoginRepository(AccountStore(applicationContext), applicationContext.resources),
            CollectionRepository(database.collectionDao(), applicationContext.resources),
            applicationContext.resources
        )
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContent {
            VocabularyTrainerTheme {
                AppScreen(
                    viewModel = viewModel,
                    onOpenLogin = { url ->
                        CustomTabsIntent.Builder()
                            .setShowTitle(true)
                            .build()
                            .launchUrl(this, url.toUri())
                    }
                )
            }
        }
    }
}

@Composable
fun AppScreen(onOpenLogin: (String) -> Unit, viewModel: MainViewModel) {
    val state by viewModel.state.collectAsState()
    val collections by viewModel.collections.collectAsState(initial = emptyList())
    val entries by viewModel.entries.collectAsState()
    val dashboardStatistics by viewModel.dashboardStatistics.collectAsState(initial = null)
    val collectionStatistics by viewModel.collectionStatistics.collectAsState()
    val selectedCollection = collections.firstOrNull { it.id == state.selectedCollectionId }

    Scaffold(modifier = Modifier.fillMaxSize()) { padding ->
        Column(
            modifier = Modifier.fillMaxSize().padding(padding).padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            state.account?.let { Text(stringResource(R.string.signed_in_as, it.loginName)) }
            state.message?.let { Text(it) }
            if (state.busy) CircularProgressIndicator()

            when {
                state.account == null -> LoginScreen(state, viewModel, onOpenLogin)
                state.screen == AppScreen.DASHBOARD -> DashboardScreen(
                    collections = collections,
                    statistics = dashboardStatistics,
                    state = state,
                    onOpenCollection = viewModel::openCollection,
                    onCreateCollection = viewModel::createCollection,
                    onUpdateCollection = viewModel::updateCollection,
                    onDeleteCollection = viewModel::deleteCollection,
                    onRefresh = { state.account?.let(viewModel::refreshCollections) },
                    onSignOut = viewModel::signOut
                )
                state.screen == AppScreen.COLLECTION && selectedCollection != null -> CollectionScreen(
                    collection = selectedCollection,
                    collections = collections,
                    entries = entries,
                    statisticsJson = collectionStatistics?.boxCountsJson,
                    entryProgressJson = collectionStatistics?.entryProgressJson,
                    accuracy = collectionStatistics?.accuracyPercent,
                    progress = collectionStatistics?.learningProgressPercent,
                    busy = state.busy,
                    onBack = viewModel::openDashboard,
                    onStudy = viewModel::openStudySetup,
                    onCreateEntry = viewModel::createEntry,
                    onUpdateEntry = viewModel::updateEntry,
                    onDeleteEntry = viewModel::deleteEntry,
                    onDeleteEntries = viewModel::deleteEntries,
                    onMoveEntries = viewModel::moveEntries
                )
                state.screen == AppScreen.STUDY_SETUP && selectedCollection != null -> StudySetupScreen(
                    collection = selectedCollection,
                    settings = state.studySettings,
                    busy = state.busy,
                    onBack = viewModel::openCollectionBack,
                    onStart = viewModel::startStudySession
                )
                state.screen == AppScreen.STUDY -> StudySessionScreen(state, viewModel)
                state.screen == AppScreen.STUDY_SUMMARY -> StudySummaryScreen(state, viewModel)
            }
        }
    }
}

@Composable
private fun LoginScreen(
    state: com.kisoft.vocabularytrainer.ui.MainState,
    viewModel: MainViewModel,
    onOpenLogin: (String) -> Unit
) {
    var serverUrl by remember(state.serverUrl) { mutableStateOf(state.serverUrl) }
    Text(stringResource(R.string.connect_nextcloud))
    OutlinedTextField(
        value = serverUrl,
        onValueChange = { serverUrl = it },
        modifier = Modifier.fillMaxWidth(),
        label = { Text(stringResource(R.string.server_url)) },
        singleLine = true
    )
    Button(
        onClick = { viewModel.startLogin(serverUrl) },
        enabled = !state.busy,
        modifier = Modifier.fillMaxWidth()
    ) { Text(stringResource(R.string.sign_in)) }
    state.pendingLogin?.let { pending ->
        Button(onClick = { onOpenLogin(pending.loginUrl) }, modifier = Modifier.fillMaxWidth()) {
            Text(stringResource(R.string.open_nextcloud_sign_in))
        }
        Button(
            onClick = viewModel::completeLogin,
            enabled = !state.busy,
            modifier = Modifier.fillMaxWidth()
        ) { Text(stringResource(R.string.completed_sign_in)) }
    }
}

@Composable
private fun ColumnScope.DashboardScreen(
    collections: List<CollectionEntity>,
    statistics: com.kisoft.vocabularytrainer.data.db.DashboardStatisticsEntity?,
    state: com.kisoft.vocabularytrainer.ui.MainState,
    onOpenCollection: (Int) -> Unit,
    onCreateCollection: (CollectionWriteRequest) -> Unit,
    onUpdateCollection: (Int, CollectionWriteRequest) -> Unit,
    onDeleteCollection: (Int) -> Unit,
    onRefresh: () -> Unit,
    onSignOut: () -> Unit
) {
    var showCreate by remember { mutableStateOf(false) }
    var editCollection by remember { mutableStateOf<CollectionEntity?>(null) }
    var deleteCollection by remember { mutableStateOf<CollectionEntity?>(null) }

    Text(stringResource(R.string.app_title))
    statistics?.let {
        Card(modifier = Modifier.fillMaxWidth()) {
            Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                Text(pluralStringResource(R.plurals.word_pairs_count, it.wordPairCount, it.wordPairCount))
                Text(stringResource(R.string.learning_progress, it.learningProgressPercent.percentText()))
                Text(stringResource(R.string.answer_accuracy, it.accuracyPercent.percentText()))
                Text(stringResource(R.string.practiced_today, it.practicedTodayCount))
            }
        }
    }
    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        Button(onClick = { showCreate = true }, enabled = !state.busy) {
            Text(stringResource(R.string.new_collection))
        }
        OutlinedButton(onClick = onRefresh, enabled = !state.busy) { Text(stringResource(R.string.refresh)) }
        OutlinedButton(onClick = onSignOut, enabled = !state.busy) { Text(stringResource(R.string.sign_out)) }
    }
    LazyColumn(
        modifier = Modifier.weight(1f),
        verticalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        items(collections, key = { it.id }) { collection ->
            Card(modifier = Modifier.fillMaxWidth()) {
                Column(Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    Column(
                        Modifier.fillMaxWidth().clickable { onOpenCollection(collection.id) },
                        verticalArrangement = Arrangement.spacedBy(4.dp)
                    ) {
                        Text(collection.name)
                        Text(stringResource(R.string.collection_languages, collection.sourceLanguage, collection.targetLanguage))
                        Text(
                            pluralStringResource(
                                R.plurals.collection_pair_progress,
                                collection.entryCount,
                                collection.entryCount,
                                collection.learningProgressPercent.percentText()
                            )
                        )
                    }
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        TextButton(onClick = { editCollection = collection }, enabled = !state.busy) {
                            Text(stringResource(R.string.edit))
                        }
                        TextButton(onClick = { deleteCollection = collection }, enabled = !state.busy) {
                            Text(stringResource(R.string.delete))
                        }
                    }
                }
            }
        }
    }
    if (showCreate || editCollection != null) {
        CollectionDialog(
            collection = editCollection,
            onDismiss = { showCreate = false; editCollection = null },
            onSave = { request ->
                val existing = editCollection
                if (existing == null) onCreateCollection(request) else onUpdateCollection(existing.id, request)
                showCreate = false
                editCollection = null
            }
        )
    }
    deleteCollection?.let { collection ->
        AlertDialog(
            onDismissRequest = { deleteCollection = null },
            title = { Text(stringResource(R.string.delete_collection_title, collection.name)) },
            text = { Text(stringResource(R.string.delete_collection_message)) },
            confirmButton = {
                TextButton(onClick = { onDeleteCollection(collection.id); deleteCollection = null }) {
                    Text(stringResource(R.string.delete))
                }
            },
            dismissButton = {
                TextButton(onClick = { deleteCollection = null }) { Text(stringResource(R.string.cancel)) }
            }
        )
    }
}

@Composable
private fun CollectionDialog(
    collection: CollectionEntity?,
    onDismiss: () -> Unit,
    onSave: (CollectionWriteRequest) -> Unit
) {
    var name by remember(collection?.id) { mutableStateOf(collection?.name.orEmpty()) }
    var source by remember(collection?.id) { mutableStateOf(collection?.sourceLanguage.orEmpty()) }
    var target by remember(collection?.id) { mutableStateOf(collection?.targetLanguage.orEmpty()) }
    var boxes by remember(collection?.id) { mutableStateOf((collection?.boxCount ?: 5).toString()) }
    AlertDialog(
        onDismissRequest = onDismiss,
        title = {
            Text(stringResource(if (collection == null) R.string.new_collection else R.string.edit_collection))
        },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                OutlinedTextField(
                    name,
                    { name = it },
                    label = { Text(stringResource(R.string.collection_name)) },
                    singleLine = true
                )
                OutlinedTextField(
                    source,
                    { source = it },
                    label = { Text(stringResource(R.string.source_language_tag)) },
                    singleLine = true
                )
                OutlinedTextField(
                    target,
                    { target = it },
                    label = { Text(stringResource(R.string.target_language_tag)) },
                    singleLine = true
                )
                OutlinedTextField(
                    boxes,
                    { boxes = it },
                    label = { Text(stringResource(R.string.study_boxes_range)) },
                    singleLine = true,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number)
                )
            }
        },
        confirmButton = {
            TextButton(
                onClick = {
                    onSave(CollectionWriteRequest(name.trim(), source.trim(), target.trim(), boxes.toIntOrNull() ?: 0))
                }
            ) { Text(stringResource(R.string.save)) }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text(stringResource(R.string.cancel)) } }
    )
}

@Composable
private fun ColumnScope.CollectionScreen(
    collection: CollectionEntity,
    collections: List<CollectionEntity>,
    entries: List<EntryEntity>,
    statisticsJson: String?,
    entryProgressJson: String?,
    accuracy: Double?,
    progress: Double?,
    busy: Boolean,
    onBack: () -> Unit,
    onStudy: () -> Unit,
    onCreateEntry: (EntryWriteRequest) -> Unit,
    onUpdateEntry: (Int, EntryWriteRequest) -> Unit,
    onDeleteEntry: (Int) -> Unit,
    onDeleteEntries: (List<Int>) -> Unit,
    onMoveEntries: (Int, List<Int>) -> Unit
) {
    var showNewEntry by remember(collection.id) { mutableStateOf(false) }
    var editEntry by remember(collection.id) { mutableStateOf<EntryEntity?>(null) }
    var deleteEntry by remember(collection.id) { mutableStateOf<EntryEntity?>(null) }
    var selectedIds by remember(collection.id) { mutableStateOf(setOf<Int>()) }
    var selectedDirection by remember(collection.id) { mutableStateOf("source_to_target") }
    var selectedBox by remember(collection.id) { mutableStateOf<Int?>(null) }
    var showMove by remember(collection.id) { mutableStateOf(false) }
    var confirmBulkDelete by remember(collection.id) { mutableStateOf(false) }
    val visibleEntries = filterEntriesByBox(entries, entryProgressJson, selectedDirection, selectedBox)

    TextButton(onClick = onBack) { Text(stringResource(R.string.back_to_collections)) }
    Text(collection.name)
    Text(
        pluralStringResource(
            R.plurals.collection_details,
            entries.size,
            collection.sourceLanguage,
            collection.targetLanguage,
            entries.size
        )
    )
    Text(
        stringResource(
            R.string.progress_and_accuracy,
            stringResource(R.string.learning_progress, progress.percentText()),
            stringResource(R.string.answer_accuracy, accuracy.percentText())
        )
    )
    BoxDistribution(
        statisticsJson = statisticsJson,
        totalEntries = entries.size,
        selectedDirection = selectedDirection,
        selectedBox = selectedBox,
        onDirectionSelected = {
            selectedDirection = it
            selectedIds = emptySet()
        },
        onBoxSelected = {
            selectedBox = it
            selectedIds = emptySet()
        }
    )
    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        Button(onClick = onStudy, enabled = entries.isNotEmpty() && !busy) {
            Text(stringResource(R.string.study))
        }
        OutlinedButton(onClick = { showNewEntry = true }, enabled = !busy) {
            Text(stringResource(R.string.add_pair))
        }
    }
    if (selectedIds.isNotEmpty()) {
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
            Text(stringResource(R.string.selected_count, selectedIds.size))
            TextButton(onClick = { showMove = true }, enabled = !busy) { Text(stringResource(R.string.move)) }
            TextButton(onClick = { confirmBulkDelete = true }, enabled = !busy) {
                Text(stringResource(R.string.delete))
            }
            TextButton(onClick = { selectedIds = emptySet() }) { Text(stringResource(R.string.clear)) }
        }
    }
    if (visibleEntries.isEmpty()) {
        Text(stringResource(if (entries.isEmpty()) R.string.no_word_pairs_yet else R.string.no_word_pairs_in_box))
    }
    LazyColumn(
        modifier = Modifier.weight(1f),
        verticalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        items(visibleEntries, key = { it.id }) { entry ->
            Card(modifier = Modifier.fillMaxWidth()) {
                Row(
                    Modifier.padding(8.dp).fillMaxWidth(),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Checkbox(
                        checked = entry.id in selectedIds,
                        enabled = !busy,
                        onCheckedChange = { checked ->
                            selectedIds = if (checked) selectedIds + entry.id else selectedIds - entry.id
                        }
                    )
                    Column(Modifier.weight(1f)) {
                        Text(entry.sourceTerm)
                        Text(entry.targetTerm)
                        if (!entry.notes.isNullOrBlank()) Text(entry.notes)
                    }
                    Column {
                        TextButton(onClick = { editEntry = entry }, enabled = !busy) {
                            Text(stringResource(R.string.edit))
                        }
                        TextButton(onClick = { deleteEntry = entry }, enabled = !busy) {
                            Text(stringResource(R.string.delete))
                        }
                    }
                }
            }
        }
    }
    if (showNewEntry || editEntry != null) {
        EntryDialog(
            entry = editEntry,
            sourceLanguage = collection.sourceLanguage,
            targetLanguage = collection.targetLanguage,
            onDismiss = { showNewEntry = false; editEntry = null },
            onSave = { request ->
                val existing = editEntry
                if (existing == null) onCreateEntry(request) else onUpdateEntry(existing.id, request)
                showNewEntry = false
                editEntry = null
            }
        )
    }
    deleteEntry?.let { entry ->
        AlertDialog(
            onDismissRequest = { deleteEntry = null },
            title = { Text(stringResource(R.string.delete_word_pair_title)) },
            text = { Text("${entry.sourceTerm} → ${entry.targetTerm}") },
            confirmButton = {
                TextButton(onClick = { onDeleteEntry(entry.id); deleteEntry = null }) {
                    Text(stringResource(R.string.delete))
                }
            },
            dismissButton = {
                TextButton(onClick = { deleteEntry = null }) { Text(stringResource(R.string.cancel)) }
            }
        )
    }
    if (confirmBulkDelete) {
        AlertDialog(
            onDismissRequest = { confirmBulkDelete = false },
            title = {
                Text(pluralStringResource(R.plurals.delete_word_pairs_title, selectedIds.size, selectedIds.size))
            },
            confirmButton = {
                TextButton(onClick = {
                    onDeleteEntries(selectedIds.toList())
                    selectedIds = emptySet()
                    confirmBulkDelete = false
                }) { Text(stringResource(R.string.delete)) }
            },
            dismissButton = {
                TextButton(onClick = { confirmBulkDelete = false }) { Text(stringResource(R.string.cancel)) }
            }
        )
    }
    if (showMove) {
        val targets = collections.filter { it.id != collection.id }
        AlertDialog(
            onDismissRequest = { showMove = false },
            title = {
                Text(pluralStringResource(R.plurals.move_word_pairs_title, selectedIds.size, selectedIds.size))
            },
            text = {
                if (targets.isEmpty()) Text(stringResource(R.string.create_another_collection))
                else Column {
                    targets.forEach { target ->
                        TextButton(
                            onClick = {
                                onMoveEntries(target.id, selectedIds.toList())
                                selectedIds = emptySet()
                                showMove = false
                            }
                        ) { Text(target.name) }
                    }
                }
            },
            confirmButton = {
                TextButton(onClick = { showMove = false }) { Text(stringResource(R.string.close)) }
            }
        )
    }
}

@Composable
private fun EntryDialog(
    entry: EntryEntity?,
    sourceLanguage: String,
    targetLanguage: String,
    onDismiss: () -> Unit,
    onSave: (EntryWriteRequest) -> Unit
) {
    var source by remember(entry?.id) { mutableStateOf(entry?.sourceTerm.orEmpty()) }
    var target by remember(entry?.id) { mutableStateOf(entry?.targetTerm.orEmpty()) }
    var notes by remember(entry?.id) { mutableStateOf(entry?.notes.orEmpty()) }
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text(stringResource(if (entry == null) R.string.add_word_pair else R.string.edit_word_pair)) },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                OutlinedTextField(source, { source = it }, label = { Text(sourceLanguage) }, singleLine = true)
                OutlinedTextField(target, { target = it }, label = { Text(targetLanguage) }, singleLine = true)
                OutlinedTextField(notes, { notes = it }, label = { Text(stringResource(R.string.notes_optional)) })
            }
        },
        confirmButton = {
            TextButton(onClick = { onSave(EntryWriteRequest(source.trim(), target.trim(), notes.trim())) }) {
                Text(stringResource(R.string.save))
            }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text(stringResource(R.string.cancel)) } }
    )
}

@Composable
private fun BoxDistribution(
    statisticsJson: String?,
    totalEntries: Int,
    selectedDirection: String,
    selectedBox: Int?,
    onDirectionSelected: (String) -> Unit,
    onBoxSelected: (Int?) -> Unit
) {
    val json = statisticsJson ?: return
    val counts = remember(json) { JsonParser.parseString(json).asJsonObject }
    val directionCounts = counts.getAsJsonArray(selectedDirection)
    Card(modifier = Modifier.fillMaxWidth()) {
        Column(Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Text(stringResource(R.string.box_distribution))
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                FilterChip(
                    selected = selectedDirection == "source_to_target",
                    onClick = { onDirectionSelected("source_to_target") },
                    label = { Text(stringResource(R.string.source_to_target)) }
                )
                FilterChip(
                    selected = selectedDirection == "target_to_source",
                    onClick = { onDirectionSelected("target_to_source") },
                    label = { Text(stringResource(R.string.target_to_source)) }
                )
            }
            Row(
                modifier = Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                FilterChip(
                    selected = selectedBox == null,
                    onClick = { onBoxSelected(null) },
                    label = { Text(stringResource(R.string.all_pairs_count, totalEntries)) }
                )
                directionCounts.forEachIndexed { index, countValue ->
                    val box = index + 1
                    val count = countValue.asInt
                    val share = if (totalEntries == 0) 0 else (count * 100.0 / totalEntries).toInt()
                    FilterChip(
                        selected = selectedBox == box,
                        onClick = { onBoxSelected(box) },
                        label = { Text(stringResource(R.string.box_count_share, box, count, share)) }
                    )
                }
            }
        }
    }
}

@Composable
private fun ColumnScope.StudySetupScreen(
    collection: CollectionEntity,
    settings: StudySettingsDto?,
    busy: Boolean,
    onBack: () -> Unit,
    onStart: (StudySessionRequest) -> Unit
) {
    var direction by remember(collection.id) { mutableStateOf("source_to_target") }
    var mode by remember(collection.id) { mutableStateOf("typed") }
    var selection by remember(collection.id) { mutableStateOf("automatic") }
    var box by remember(collection.id) { mutableStateOf("1") }
    var errorRate by remember(collection.id) { mutableStateOf("50") }
    var days by remember(collection.id) { mutableStateOf("30") }
    var startWithNew by remember(collection.id) { mutableStateOf(true) }
    var includeFinalBox by remember(collection.id) { mutableStateOf(false) }
    var answerDelay by remember(collection.id) { mutableStateOf("5") }
    var timeLimit by remember(collection.id) { mutableStateOf("0") }
    LaunchedEffect(settings) {
        settings?.let {
            startWithNew = it.startWithNew
            includeFinalBox = it.includeFinalBox
            answerDelay = it.answerDelaySeconds.toString()
            timeLimit = it.questionTimeLimitSeconds.toString()
        }
    }

    Column(
        modifier = Modifier.weight(1f).verticalScroll(rememberScrollState()),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        TextButton(onClick = onBack) { Text(stringResource(R.string.back_to_collection)) }
        Text(stringResource(R.string.study_collection, collection.name))
        OptionRow(stringResource(R.string.direction), direction, listOf("source_to_target", "target_to_source")) {
            direction = it
        }
        OptionRow(stringResource(R.string.mode), mode, listOf("typed", "multiple-choice", "mixed")) { mode = it }
        OptionRow(
            stringResource(R.string.selection),
            selection,
            listOf("automatic", "new", "box", "error-rate", "not-practiced")
        ) { selection = it }
        when (selection) {
            "box" -> NumericInput(stringResource(R.string.box_number, collection.boxCount), box) { box = it }
            "error-rate" -> NumericInput(stringResource(R.string.minimum_error_rate), errorRate) { errorRate = it }
            "not-practiced" -> NumericInput(stringResource(R.string.not_practiced_days), days) { days = it }
        }
        if (selection == "automatic") {
            ToggleRow(stringResource(R.string.start_with_new_pairs), startWithNew) { startWithNew = it }
            ToggleRow(stringResource(R.string.include_final_box), includeFinalBox) { includeFinalBox = it }
        }
        NumericInput(stringResource(R.string.answer_display_delay), answerDelay) { answerDelay = it }
        NumericInput(stringResource(R.string.question_time_limit), timeLimit) { timeLimit = it }
        Text(stringResource(R.string.multiple_choice_minimum))
        Button(
            onClick = {
                onStart(
                    StudySessionRequest(
                        direction = direction,
                        mode = mode,
                        selection = selection,
                        box = if (selection == "box") box.toIntOrNull() else null,
                        errorRate = if (selection == "error-rate") errorRate.toIntOrNull() else null,
                        days = if (selection == "not-practiced") days.toIntOrNull() else null,
                        startWithNew = startWithNew,
                        includeFinalBox = includeFinalBox,
                        answerDelaySeconds = answerDelay.toIntOrNull() ?: 5,
                        questionTimeLimitSeconds = timeLimit.toIntOrNull() ?: 0
                    )
                )
            },
            enabled = !busy,
            modifier = Modifier.fillMaxWidth()
        ) { Text(stringResource(R.string.start_studying)) }
    }
}

@Composable
private fun OptionRow(label: String, current: String, options: List<String>, onSelected: (String) -> Unit) {
    Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
        Text(label)
        Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
            options.forEach { option ->
                if (option == current) Button(onClick = {}) {
                    Text(stringResource(optionStringResource(option)))
                } else OutlinedButton(onClick = { onSelected(option) }) {
                    Text(stringResource(optionStringResource(option)))
                }
            }
        }
    }
}

@Composable
private fun NumericInput(label: String, value: String, onChange: (String) -> Unit) {
    OutlinedTextField(
        value = value,
        onValueChange = onChange,
        label = { Text(label) },
        singleLine = true,
        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
        modifier = Modifier.fillMaxWidth()
    )
}

@Composable
private fun ToggleRow(label: String, checked: Boolean, onCheckedChange: (Boolean) -> Unit) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Checkbox(checked, onCheckedChange)
        Text(label)
    }
}

@Composable
private fun ColumnScope.StudySessionScreen(
    state: com.kisoft.vocabularytrainer.ui.MainState,
    viewModel: MainViewModel
) {
    val session = state.studySession ?: return
    val question = session.questions.getOrNull(state.questionIndex) ?: return
    val keyboardController = LocalSoftwareKeyboardController.current
    var paused by remember(session, state.questionIndex) { mutableStateOf(false) }
    var answer by remember(session, state.questionIndex) { mutableStateOf("") }
    val timerProgress = remember(session, state.questionIndex) { Animatable(1f) }
    val totalSeconds = if (state.studyAnswer == null) {
        session.questionTimeLimitSeconds
    } else {
        session.answerDelaySeconds
    }

    LaunchedEffect(state.questionIndex, state.studyAnswer) {
        timerProgress.snapTo(1f)
    }
    LaunchedEffect(paused, state.busy, state.questionIndex, state.studyAnswer) {
        if (paused || state.busy || (state.studyAnswer == null && session.questionTimeLimitSeconds == 0)) return@LaunchedEffect
        val remainingMillis = (timerProgress.value * totalSeconds * 1_000).toInt()
        if (remainingMillis > 0) {
            timerProgress.animateTo(
                targetValue = 0f,
                animationSpec = tween(durationMillis = remainingMillis, easing = LinearEasing)
            )
        }
        if (state.studyAnswer == null) viewModel.submitStudyAnswer("")
        else viewModel.advanceStudy()
    }

    Column(
        modifier = Modifier.weight(1f).verticalScroll(rememberScrollState()),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        Text(stringResource(R.string.question_of, state.questionIndex + 1, session.totalQuestions))
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
            if (totalSeconds > 0) {
                OutlinedButton(onClick = { paused = !paused }) {
                    Text(stringResource(if (paused) R.string.resume else R.string.pause))
                }
            }
            TextButton(onClick = viewModel::finishStudy) { Text(stringResource(R.string.exit)) }
        }
        if (totalSeconds > 0) {
            LinearProgressIndicator(
                progress = { timerProgress.value },
                modifier = Modifier.fillMaxWidth()
            )
        }
        Spacer(Modifier.height(16.dp))
        Card(modifier = Modifier.fillMaxWidth()) {
            Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Text(question.prompt)
                state.studyAnswer?.let { result ->
                    Text(stringResource(if (result.correct) R.string.correct else R.string.not_quite))
                    if (!result.correct) Text(stringResource(R.string.answer_value, result.correctAnswer))
                    Text(stringResource(R.string.box_value, result.box))
                } ?: if (question.type == "multiple-choice") {
                    question.choices.forEach { choice ->
                        OutlinedButton(
                            onClick = { viewModel.submitStudyAnswer(choice) },
                            enabled = !state.busy,
                            modifier = Modifier.fillMaxWidth()
                        ) { Text(choice) }
                    }
                } else {
                    OutlinedTextField(
                        value = answer,
                        onValueChange = { answer = it },
                        label = { Text(stringResource(R.string.your_answer)) },
                        singleLine = true,
                        keyboardOptions = KeyboardOptions(imeAction = ImeAction.Done),
                        keyboardActions = KeyboardActions(
                            onDone = {
                                viewModel.submitStudyAnswer(answer)
                                keyboardController?.hide()
                            }
                        ),
                        enabled = !state.busy,
                        modifier = Modifier.fillMaxWidth()
                    )
                    Button(
                        onClick = { viewModel.submitStudyAnswer(answer) },
                        enabled = !state.busy,
                        modifier = Modifier.fillMaxWidth()
                    ) { Text(stringResource(R.string.check_answer)) }
                }
            }
        }
        if (state.studyAnswer != null) {
            TextButton(onClick = viewModel::advanceStudy) { Text(stringResource(R.string.next_now)) }
        }
    }
}

@Composable
private fun StudySummaryScreen(state: com.kisoft.vocabularytrainer.ui.MainState, viewModel: MainViewModel) {
    Text(stringResource(R.string.study_complete))
    Text(stringResource(R.string.correct_answers_count, state.correctAnswers, state.studySession?.totalQuestions ?: 0))
    Button(onClick = viewModel::finishStudy) { Text(stringResource(R.string.back_to_collection)) }
}

private fun Double?.percentText(): String = this?.let { "%.0f%%".format(it) } ?: "—"

private fun optionStringResource(option: String): Int = when (option) {
    "source_to_target" -> R.string.source_to_target
    "target_to_source" -> R.string.target_to_source
    "typed" -> R.string.typed
    "multiple-choice" -> R.string.multiple_choice
    "mixed" -> R.string.mixed
    "automatic" -> R.string.automatic
    "new" -> R.string.new_pairs
    "box" -> R.string.pairs_in_box
    "error-rate" -> R.string.error_rate
    "not-practiced" -> R.string.not_practiced
    else -> error("Unknown study option: $option")
}
