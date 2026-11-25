package com.kisoft.vocabularytrainer

import com.kisoft.vocabularytrainer.data.db.EntryEntity
import com.kisoft.vocabularytrainer.ui.filterEntriesByBox
import org.junit.Assert.assertEquals
import org.junit.Test

class EntryFilteringTest {
    private val entries = listOf(
        EntryEntity(1, 7, "one", "eins", null),
        EntryEntity(2, 7, "two", "zwei", null)
    )

    @Test
    fun noSelectedBoxShowsAllEntriesWithoutProgressCache() {
        assertEquals(entries, filterEntriesByBox(entries, null, "source_to_target", null))
    }

    @Test
    fun selectedBoxFiltersByDirection() {
        val progress = """
            {
              "1": {"source_to_target": 1, "target_to_source": 2},
              "2": {"source_to_target": 2, "target_to_source": 1}
            }
        """.trimIndent()

        assertEquals(
            listOf(entries[0]),
            filterEntriesByBox(entries, progress, "source_to_target", 1)
        )
        assertEquals(
            listOf(entries[1]),
            filterEntriesByBox(entries, progress, "target_to_source", 1)
        )
    }

    @Test
    fun selectedBoxWithNoProgressMatchesReturnsEmptyList() {
        assertEquals(
            emptyList<EntryEntity>(),
            filterEntriesByBox(entries, "{}", "source_to_target", 1)
        )
    }
}
