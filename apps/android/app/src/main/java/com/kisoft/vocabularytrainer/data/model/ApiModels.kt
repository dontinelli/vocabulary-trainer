package com.kisoft.vocabularytrainer.data.model

import com.google.gson.JsonElement

data class CollectionWriteRequest(
    val name: String,
    val sourceLanguage: String,
    val targetLanguage: String,
    val boxCount: Int
)

data class EntryDto(
    val id: Int,
    val sourceTerm: String,
    val targetTerm: String,
    val notes: String?
)

data class EntryWriteRequest(
    val sourceTerm: String,
    val targetTerm: String,
    val notes: String
)

data class BulkEntriesRequest(
    val entryIds: List<Int>,
    val targetCollectionId: Int? = null
)

data class ActionResponse(val success: Boolean)
data class CountResponse(val deleted: Int? = null, val moved: Int? = null)

data class DashboardStatisticsDto(
    val wordPairCount: Int,
    val learningProgressPercent: Double?,
    val accuracyPercent: Double?,
    val practicedTodayCount: Int
)

data class CollectionStatisticsDto(
    val boxCount: Int,
    val boxCounts: Map<String, List<Int>>,
    val entryProgress: JsonElement,
    val accuracyPercent: Double?,
    val learningProgressPercent: Double?
)

data class StudySettingsDto(
    val startWithNew: Boolean,
    val includeFinalBox: Boolean,
    val answerDelaySeconds: Int,
    val questionTimeLimitSeconds: Int
)

data class StudySessionRequest(
    val direction: String,
    val mode: String,
    val selection: String,
    val box: Int?,
    val errorRate: Int?,
    val days: Int?,
    val startWithNew: Boolean,
    val includeFinalBox: Boolean,
    val answerDelaySeconds: Int,
    val questionTimeLimitSeconds: Int
)

data class StudyQuestionDto(
    val entryId: Int,
    val type: String,
    val prompt: String,
    val choices: List<String>
)

data class StudySessionDto(
    val direction: String,
    val mode: String,
    val answerDelaySeconds: Int,
    val questionTimeLimitSeconds: Int,
    val totalQuestions: Int,
    val questions: List<StudyQuestionDto>
)

data class StudyAnswerRequest(val direction: String, val answer: String)

data class StudyAnswerDto(
    val correct: Boolean,
    val correctAnswer: String,
    val box: Int,
    val answered: Int,
    val answeredCorrectly: Int
)
