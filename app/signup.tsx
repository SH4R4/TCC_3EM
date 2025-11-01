import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { Link, useRouter } from 'expo-router';
import { useState } from 'react';
import {
  Dimensions,
  Keyboard,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableWithoutFeedback,
  View,
} from 'react-native';
import api from '../components/api';

const windowWidth = Dimensions.get('window').width;
const windowHeight = Dimensions.get('window').height;

function heightPercent(percentage: number) {
  return windowHeight * (percentage / 100);
}

function widthPercent(percentage: number) {
  return windowWidth * (percentage / 100);
}

export default function SignUp() {
  const router = useRouter();

  const [nome, setNome] = useState('');
  const [email, setEmail] = useState('');
  const [senha, setSenha] = useState('');
  const [errorMessage, setErrorMessage] = useState('');

  const handleSubmit = async () => {
    setErrorMessage('');

    if (!nome || !email || !senha) {
      setErrorMessage('Preencha todos os campos');
      return;
    }

    if (senha.length < 6) {
      setErrorMessage('A senha deve ter pelo menos 6 caracteres');
      return;
    }

    try {
      const response = await api.noAuth.post('/auth.php?endpoint=cadastro', {
        nome,
        email,
        senha,
      });

      if (response?.token) {
        await AsyncStorage.setItem('token', response.token);
        await AsyncStorage.setItem('userId', response.id.toString());
        router.navigate('/termos');
        return;
      }

      if (response?.id) {
        await AsyncStorage.setItem('userId', response.id.toString());
        router.navigate('/termos');
      }
    } catch (error: any) {
      setErrorMessage(error.message || 'Erro ao fazer cadastro');
    }
  };

  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
    >
      <View style={styles.backButtonBackground} />
      <Pressable style={styles.backButton} onPress={() => router.push('/')}>
        <Ionicons name="arrow-back" size={20} color="white" />
      </Pressable>

      <TouchableWithoutFeedback onPress={Keyboard.dismiss}>
        <ScrollView
          contentContainerStyle={styles.scrollContent}
          keyboardShouldPersistTaps="handled"
        >
          <View style={styles.innerContainer}>
            <Text style={styles.title}>
              Inicie sua jornada rumo a uma alimentação saudável e um estado
              emocional equilibrado
            </Text>

            {errorMessage !== '' && (
              <View style={styles.errorBox}>
                <Text style={styles.errorIcon}>⚠️</Text>
                <Text style={styles.errorText}>{errorMessage}</Text>
              </View>
            )}

            <View style={styles.form}>
              <View style={styles.items}>
                <Ionicons
                  name="person-outline"
                  size={20}
                  color="#666"
                  style={styles.icon}
                />
                <TextInput
                  style={styles.input}
                  value={nome}
                  onChangeText={setNome}
                  placeholder="Nome"
                  placeholderTextColor="#888"
                  returnKeyType="next"
                />
              </View>

              <View style={styles.items}>
                <Ionicons
                  name="mail-outline"
                  size={20}
                  color="#666"
                  style={styles.icon}
                />
                <TextInput
                  style={styles.input}
                  value={email}
                  onChangeText={setEmail}
                  placeholder="Email"
                  placeholderTextColor="#888"
                  returnKeyType="next"
                />
              </View>

              <View style={styles.items}>
                <Ionicons
                  name="lock-closed-outline"
                  size={20}
                  color="#666"
                  style={styles.icon}
                />
                <TextInput
                  style={styles.input}
                  value={senha}
                  onChangeText={setSenha}
                  secureTextEntry
                  placeholder="Senha"
                  placeholderTextColor="#888"
                  returnKeyType="done"
                  onSubmitEditing={handleSubmit}
                />
              </View>

              <Pressable style={styles.button} onPress={handleSubmit}>
                <Text style={styles.buttonText}>Cadastrar</Text>
              </Pressable>
            </View>

            <View style={styles.goto}>
              <Text style={styles.gotoText}>Já possui uma conta? </Text>
              <Link href="/">
                <Text style={styles.gotoTextLink}>Entrar</Text>
              </Link>
            </View>
          </View>
        </ScrollView>
      </TouchableWithoutFeedback>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  backButton: {
    position: 'absolute',
    top: 20,
    left: 20,
    width: 35,
    height: 35,
    borderRadius: 20,
    backgroundColor: '#007912',
    justifyContent: 'center',
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: {
      width: 0,
      height: 2,
    },
    shadowOpacity: 0.3,
    shadowRadius: 4,
    elevation: 5,
    zIndex: 10,
  },
  backButtonBackground: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    height: 60,
    backgroundColor: '#ecfcec',
    zIndex: 9,
  },
  container: {
    flex: 1,
    backgroundColor: '#ecfcec',
  },
  scrollContent: {
    flexGrow: 1,
  },
  innerContainer: {
    flex: 1,
    width: '100%',
    paddingHorizontal: 20,
    paddingTop: 85,
    paddingBottom: 40,
    alignItems: 'center',
    justifyContent: 'flex-start',
  },
  title: {
    fontSize: 38,
    fontWeight: 'bold',
    color: '#088c1c',
    textAlign: 'left',
    marginBottom: 30,
    paddingHorizontal: 10,
    width: '100%',
  },
  errorBox: {
    backgroundColor: '#FFEBEE',
    borderLeftWidth: 4,
    borderLeftColor: '#F44336',
    padding: 15,
    borderRadius: 8,
    marginBottom: 20,
    width: '100%',
    flexDirection: 'row',
    alignItems: 'center',
  },
  errorIcon: {
    fontSize: 20,
    marginRight: 10,
  },
  errorText: {
    color: '#C62828',
    fontSize: 14,
    fontWeight: '600',
    flex: 1,
  },
  form: {
    width: '100%',
    alignItems: 'center',
    gap: 15,
    marginBottom: 20,
  },
  items: {
    width: '100%',
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#dadada',
    borderRadius: 15,
    paddingHorizontal: 15,
    height: 50,
    shadowColor: '#000',
    shadowOffset: {
      width: 0,
      height: 2,
    },
    shadowOpacity: 0.1,
    shadowRadius: 3,
    elevation: 3,
  },
  icon: {
    marginRight: 10,
  },
  input: {
    flex: 1,
    height: '100%',
    color: '#000000',
    fontSize: 16,
  },
  button: {
    width: '80%',
    height: 50,
    borderRadius: 20,
    backgroundColor: '#007912',
    justifyContent: 'center',
    alignItems: 'center',
    marginTop: 10,
    shadowColor: '#000',
    shadowOffset: {
      width: 0,
      height: 2,
    },
    shadowOpacity: 0.2,
    shadowRadius: 3,
    elevation: 4,
  },
  buttonText: {
    fontSize: 18,
    color: 'white',
    fontWeight: 'bold',
  },
  goto: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: 10,
  },
  gotoText: {
    fontSize: 16,
    color: '#333',
  },
  gotoTextLink: {
    fontSize: 16,
    color: '#3392FF',
    fontWeight: '600',
  },
});
