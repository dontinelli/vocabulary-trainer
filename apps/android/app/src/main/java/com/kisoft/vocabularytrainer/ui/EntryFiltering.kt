package com.kisoft.vocabularytrainer.ui

import com.google.gson.JsonParser
import com.kisoft.vocabularytrainer.data.db.EntryEntity

internal fun filterEntriesByBox(
    entries: List<EntryEntity>,
    entryProgressJson: String?,
    direction: String,
    selectedBox: Int?
): List<EntryEntity> {
    if (selectedBox == null) return entries
    val progress = entryProgressJson
        ?.let { JsonParser.parseString(it) }
        ?.takeIf { it.isJsonObject }
        ?.asJsonObject
        ?: return emptyList()
    return entries.filter { entry ->
        val progressByDirection = progress.get(entry.id.toString())
            ?.takeIf { it.isJsonObject }
            ?.asJsonObject
        progressByDirection?.get(direction)
            ?.takeIf { it.isJsonPrimitive && it.asJsonPrimitive.isNumber }
            ?.asInt == selectedBox
    }
}
