import React, { useState } from 'react';
import { View, Text, StyleSheet, ScrollView, SafeAreaView, TouchableOpacity, TextInput } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';

export default function PreferencesScreen() {
  const router = useRouter();
  const { role, interests } = useLocalSearchParams();
  const interestsArray = interests ? (interests as string).split(',') : [];

  const [searchQuery, setSearchQuery] = useState('');
  const [selectedTags, setSelectedTags] = useState<string[]>([]);

  // Dummy tags to simulate narrowing down preferences
  const availableTags = [
    'Technology', 'Healthcare', 'Education', 'Construction',
    'Creative Arts', 'Finance', 'Engineering', 'Remote Work',
    'Part-Time', 'Full-Time', 'Undergraduate', 'Postgraduate',
    'Government', 'Private Sector', 'Freelance'
  ];

  const filteredTags = availableTags.filter(tag => 
    tag.toLowerCase().includes(searchQuery.toLowerCase())
  );

  const toggleTag = (tag: string) => {
    if (selectedTags.includes(tag)) {
      setSelectedTags(selectedTags.filter(t => t !== tag));
    } else {
      setSelectedTags([...selectedTags, tag]);
    }
  };

  const handleFinish = () => {
    // Navigate to the main app dashboard (which you would create later)
    // For now, let's just go back to start
    router.replace('/');
  };

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView contentContainerStyle={styles.scrollContent}>
        <View style={styles.header}>
          <Text style={styles.title}>Narrow it down</Text>
          <Text style={styles.subtitle}>
            You selected {interestsArray.join(', ')}. What specific fields or types are you interested in?
          </Text>
        </View>

        <TextInput
          style={styles.searchInput}
          placeholder="Search fields (e.g. Technology)"
          placeholderTextColor="#A0AEC0"
          value={searchQuery}
          onChangeText={setSearchQuery}
        />

        <View style={styles.tagsContainer}>
          {filteredTags.map((tag) => (
            <TouchableOpacity
              key={tag}
              style={[
                styles.tag,
                selectedTags.includes(tag) && styles.tagSelected
              ]}
              onPress={() => toggleTag(tag)}
            >
              <Text style={[
                styles.tagText,
                selectedTags.includes(tag) && styles.tagTextSelected
              ]}>
                {selectedTags.includes(tag) ? '✓ ' : '+ '}{tag}
              </Text>
            </TouchableOpacity>
          ))}
        </View>
        
        {filteredTags.length === 0 && (
          <Text style={styles.noResultsText}>No fields match your search.</Text>
        )}

      </ScrollView>

      <View style={styles.footer}>
        <TouchableOpacity 
          style={styles.button} 
          onPress={handleFinish}
        >
          <Text style={styles.buttonText}>Complete Setup</Text>
        </TouchableOpacity>
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#F7FAFC',
  },
  scrollContent: {
    padding: 24,
  },
  header: {
    marginBottom: 24,
  },
  title: {
    fontSize: 28,
    fontWeight: 'bold',
    color: '#2D3748',
    marginBottom: 8,
  },
  subtitle: {
    fontSize: 16,
    color: '#718096',
    lineHeight: 24,
  },
  searchInput: {
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#E2E8F0',
    borderRadius: 12,
    padding: 16,
    marginBottom: 24,
    fontSize: 16,
    color: '#2D3748',
  },
  tagsContainer: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  tag: {
    paddingVertical: 10,
    paddingHorizontal: 16,
    borderRadius: 24,
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#E2E8F0',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 2,
    elevation: 1,
  },
  tagSelected: {
    backgroundColor: '#4C51BF',
    borderColor: '#4C51BF',
  },
  tagText: {
    color: '#4A5568',
    fontSize: 15,
    fontWeight: '500',
  },
  tagTextSelected: {
    color: '#FFFFFF',
    fontWeight: 'bold',
  },
  noResultsText: {
    color: '#A0AEC0',
    textAlign: 'center',
    marginTop: 20,
    fontSize: 16,
  },
  footer: {
    padding: 24,
    backgroundColor: '#FFFFFF',
    borderTopWidth: 1,
    borderTopColor: '#E2E8F0',
  },
  button: {
    backgroundColor: '#4C51BF',
    padding: 16,
    borderRadius: 12,
    alignItems: 'center',
    shadowColor: '#4C51BF',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 4,
    elevation: 4,
  },
  buttonText: {
    color: '#FFFFFF',
    fontSize: 18,
    fontWeight: 'bold',
  },
});
