import React, { useState } from 'react';
import { View, Text, TextInput, TouchableOpacity, StyleSheet, KeyboardAvoidingView, Platform, SafeAreaView } from 'react-native';
import { useRouter } from 'expo-router';

export default function LoginScreen() {
  const router = useRouter();
  const [isLogin, setIsLogin] = useState(true);

  const handleSubmit = () => {
    // In a real app, perform auth logic here
    router.push('/setup');
  };

  return (
    <SafeAreaView style={styles.container}>
      <KeyboardAvoidingView 
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        style={styles.inner}
      >
        <View style={styles.header}>
          <Text style={styles.title}>{isLogin ? 'Welcome Back' : 'Create Account'}</Text>
          <Text style={styles.subtitle}>
            {isLogin ? 'Sign in to continue to Radar' : 'Sign up to get started with Radar'}
          </Text>
        </View>

        <View style={styles.form}>
          {!isLogin && (
            <TextInput
              style={styles.input}
              placeholder="Full Name"
              placeholderTextColor="#A0AEC0"
            />
          )}
          <TextInput
            style={styles.input}
            placeholder="Email Address"
            placeholderTextColor="#A0AEC0"
            keyboardType="email-address"
            autoCapitalize="none"
          />
          <TextInput
            style={styles.input}
            placeholder="Password"
            placeholderTextColor="#A0AEC0"
            secureTextEntry
          />
          
          <TouchableOpacity style={styles.button} onPress={handleSubmit}>
            <Text style={styles.buttonText}>{isLogin ? 'Sign In' : 'Sign Up'}</Text>
          </TouchableOpacity>
        </View>

        <View style={styles.footer}>
          <Text style={styles.footerText}>
            {isLogin ? "Don't have an account? " : "Already have an account? "}
          </Text>
          <TouchableOpacity onPress={() => setIsLogin(!isLogin)}>
            <Text style={styles.footerLink}>{isLogin ? 'Sign Up' : 'Sign In'}</Text>
          </TouchableOpacity>
        </View>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#F7FAFC',
  },
  inner: {
    flex: 1,
    padding: 24,
    justifyContent: 'center',
  },
  header: {
    marginBottom: 40,
  },
  title: {
    fontSize: 32,
    fontWeight: 'bold',
    color: '#2D3748',
    marginBottom: 8,
  },
  subtitle: {
    fontSize: 16,
    color: '#718096',
  },
  form: {
    marginBottom: 24,
  },
  input: {
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#E2E8F0',
    borderRadius: 12,
    padding: 16,
    marginBottom: 16,
    fontSize: 16,
    color: '#2D3748',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 2,
    elevation: 2,
  },
  button: {
    backgroundColor: '#4C51BF',
    padding: 16,
    borderRadius: 12,
    alignItems: 'center',
    marginTop: 8,
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
  footer: {
    flexDirection: 'row',
    justifyContent: 'center',
    marginTop: 16,
  },
  footerText: {
    color: '#718096',
    fontSize: 16,
  },
  footerLink: {
    color: '#4C51BF',
    fontSize: 16,
    fontWeight: 'bold',
  },
});
