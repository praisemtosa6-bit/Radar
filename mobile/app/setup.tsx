import React, { useState } from 'react';
import { View, Text, TouchableOpacity, StyleSheet, ScrollView, SafeAreaView } from 'react-native';
import { useRouter } from 'expo-router';

export default function SetupScreen() {
  const router = useRouter();
  const [selectedRole, setSelectedRole] = useState<string | null>(null);
  const [selectedInterests, setSelectedInterests] = useState<string[]>([]);

  const interests = [
    { id: 'scholarships', label: 'Scholarships', description: 'Find educational funding (Free)' },
    { id: 'gigs', label: 'Gigs', description: 'Discover short-term work opportunities' },
    { id: 'tenders', label: 'Tenders', description: 'Bid on larger projects and contracts' },
  ];

  const toggleInterest = (id: string) => {
    if (selectedInterests.includes(id)) {
      setSelectedInterests(selectedInterests.filter(i => i !== id));
    } else {
      setSelectedInterests([...selectedInterests, id]);
    }
  };

  const handleContinue = () => {
    if (selectedRole && selectedInterests.length > 0) {
      router.push({
        pathname: '/preferences',
        params: { role: selectedRole, interests: selectedInterests.join(',') }
      });
    }
  };

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView contentContainerStyle={styles.scrollContent}>
        <View style={styles.header}>
          <Text style={styles.title}>Let's customize your experience</Text>
          <Text style={styles.subtitle}>Tell us a bit about yourself so we can tailor Radar to your needs.</Text>
        </View>

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>What best describes you?</Text>
          <View style={styles.roleContainer}>
            {['Student', 'Freelancer', 'Business', 'Professional'].map((role) => (
              <TouchableOpacity
                key={role}
                style={[styles.roleChip, selectedRole === role && styles.roleChipSelected]}
                onPress={() => setSelectedRole(role)}
              >
                <Text style={[styles.roleText, selectedRole === role && styles.roleTextSelected]}>
                  {role}
                </Text>
              </TouchableOpacity>
            ))}
          </View>
        </View>

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>What are you looking for?</Text>
          <Text style={styles.sectionSubtitle}>Select all that apply</Text>
          
          {interests.map((interest) => (
            <TouchableOpacity
              key={interest.id}
              style={[
                styles.interestCard,
                selectedInterests.includes(interest.id) && styles.interestCardSelected
              ]}
              onPress={() => toggleInterest(interest.id)}
            >
              <View>
                <Text style={[
                  styles.interestTitle,
                  selectedInterests.includes(interest.id) && styles.interestTitleSelected
                ]}>
                  {interest.label}
                </Text>
                <Text style={[
                  styles.interestDescription,
                  selectedInterests.includes(interest.id) && styles.interestDescriptionSelected
                ]}>
                  {interest.description}
                </Text>
              </View>
              <View style={[
                styles.checkbox,
                selectedInterests.includes(interest.id) && styles.checkboxSelected
              ]}>
                {selectedInterests.includes(interest.id) && <View style={styles.checkboxInner} />}
              </View>
            </TouchableOpacity>
          ))}
        </View>

      </ScrollView>
      <View style={styles.footer}>
        <TouchableOpacity 
          style={[styles.button, (!selectedRole || selectedInterests.length === 0) && styles.buttonDisabled]} 
          onPress={handleContinue}
          disabled={!selectedRole || selectedInterests.length === 0}
        >
          <Text style={styles.buttonText}>Continue</Text>
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
    marginBottom: 32,
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
  section: {
    marginBottom: 32,
  },
  sectionTitle: {
    fontSize: 20,
    fontWeight: '600',
    color: '#2D3748',
    marginBottom: 8,
  },
  sectionSubtitle: {
    fontSize: 14,
    color: '#718096',
    marginBottom: 16,
  },
  roleContainer: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  roleChip: {
    paddingVertical: 10,
    paddingHorizontal: 16,
    borderRadius: 20,
    backgroundColor: '#EDF2F7',
    borderWidth: 1,
    borderColor: '#E2E8F0',
  },
  roleChipSelected: {
    backgroundColor: '#EBF4FF',
    borderColor: '#4C51BF',
  },
  roleText: {
    color: '#4A5568',
    fontWeight: '500',
  },
  roleTextSelected: {
    color: '#4C51BF',
    fontWeight: 'bold',
  },
  interestCard: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: '#FFFFFF',
    padding: 20,
    borderRadius: 16,
    marginBottom: 12,
    borderWidth: 2,
    borderColor: 'transparent',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 3,
    elevation: 2,
  },
  interestCardSelected: {
    borderColor: '#4C51BF',
    backgroundColor: '#F8FAFC',
  },
  interestTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: '#2D3748',
    marginBottom: 4,
  },
  interestTitleSelected: {
    color: '#4C51BF',
  },
  interestDescription: {
    fontSize: 14,
    color: '#718096',
  },
  interestDescriptionSelected: {
    color: '#4A5568',
  },
  checkbox: {
    width: 24,
    height: 24,
    borderRadius: 12,
    borderWidth: 2,
    borderColor: '#CBD5E0',
    justifyContent: 'center',
    alignItems: 'center',
  },
  checkboxSelected: {
    borderColor: '#4C51BF',
  },
  checkboxInner: {
    width: 12,
    height: 12,
    borderRadius: 6,
    backgroundColor: '#4C51BF',
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
  buttonDisabled: {
    backgroundColor: '#A0AEC0',
    shadowOpacity: 0,
    elevation: 0,
  },
  buttonText: {
    color: '#FFFFFF',
    fontSize: 18,
    fontWeight: 'bold',
  },
});
