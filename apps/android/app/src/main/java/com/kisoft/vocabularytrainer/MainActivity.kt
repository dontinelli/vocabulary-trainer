package com.kisoft.vocabularytrainer

import android.os.Bundle
import androidx.browser.customtabs.CustomTabsIntent
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.viewModels
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.core.net.toUri
import androidx.room.Room
import com.kisoft.vocabularytrainer.data.auth.AccountStore
import com.kisoft.vocabularytrainer.data.db.VocabularyDatabase
import com.kisoft.vocabularytrainer.data.repository.CollectionRepository
import com.kisoft.vocabularytrainer.data.repository.LoginRepository
import com.kisoft.vocabularytrainer.data.db.CollectionEntity
import com.kisoft.vocabularytrainer.ui.MainViewModel
import com.kisoft.vocabularytrainer.ui.theme.VocabularyTrainerTheme
class MainActivity : ComponentActivity() {
    private val viewModel: MainViewModel by viewModels {
        val database = Room.databaseBuilder(
            applicationContext,
            VocabularyDatabase::class.java,
            "vocabulary-trainer"
        ).build()
        MainViewModel.Factory(
            LoginRepository(AccountStore(applicationContext)),
            CollectionRepository(database.collectionDao())
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
fun AppScreen(
    onOpenLogin: (String) -> Unit,
    viewModel: MainViewModel
) {
    val state by viewModel.state.collectAsState()
    val collections by viewModel.collections.collectAsState(initial = emptyList())
    var serverUrl by remember(state.serverUrl) { mutableStateOf(state.serverUrl) }

    Scaffold(modifier = Modifier.fillMaxSize()) { innerPadding ->
        if (state.account == null) {
            Column(
                modifier = Modifier
                    .padding(innerPadding)
                    .padding(24.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                Text("Connect to Nextcloud")
                OutlinedTextField(
                    value = serverUrl,
                    onValueChange = { serverUrl = it },
                    modifier = Modifier.fillMaxWidth(),
                    label = { Text("Server URL") },
                    singleLine = true
                )
                Button(
                    onClick = { viewModel.startLogin(serverUrl) },
                    enabled = !state.busy,
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Text("Sign in")
                }

                state.pendingLogin?.let { pending ->
                    Button(
                        onClick = { onOpenLogin(pending.loginUrl) },
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Text("Open Nextcloud sign-in")
                    }
                    Button(
                        onClick = viewModel::completeLogin,
                        enabled = !state.busy,
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Text("I completed sign-in")
                    }
                }

                state.message?.let { Text(it) }
                if (state.busy) CircularProgressIndicator()
            }
        } else {
            Column(
                modifier = Modifier
                    .padding(innerPadding)
                    .padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                Text("Collections")
                Button(onClick = { viewModel.refreshCollections(state.account!!) }) {
                    Text("Refresh")
                }
                Button(onClick = viewModel::signOut) {
                    Text("Sign out")
                }
                state.message?.let { Text(it) }
                if (state.busy) CircularProgressIndicator()

                LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    items(collections, key = { it.id }) { collection ->
                        CollectionCard(collection)
                    }
                }
            }
        }
    }
}

@Composable
fun CollectionCard(collection: CollectionEntity) {
    Card(modifier = Modifier.fillMaxWidth()) {
        Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
            Text(collection.name)
            Text("${collection.sourceLanguage} -> ${collection.targetLanguage}")
            Text("${collection.entryCount} pairs")
        }
    }
}