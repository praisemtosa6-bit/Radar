import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';

export default function RootLayout() {
  return (
    <>
      <Stack>
        <Stack.Screen name="index" options={{ headerShown: false }} />
        <Stack.Screen name="setup" options={{ title: 'Account Setup', headerBackTitle: 'Back' }} />
        <Stack.Screen name="preferences" options={{ title: 'Your Preferences', headerBackTitle: 'Back' }} />
      </Stack>
      <StatusBar style="dark" />
    </>
  );
}
