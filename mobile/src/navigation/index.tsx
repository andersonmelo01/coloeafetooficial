import React from 'react';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { Ionicons } from '@expo/vector-icons';
import { colors } from '../theme';
import { BootstrapScreen } from '../screens/BootstrapScreen';
import { LoginScreen } from '../screens/LoginScreen';
import { PdvScreen } from '../screens/PdvScreen';
import { CartScreen } from '../screens/CartScreen';
import { CheckoutScreen } from '../screens/CheckoutScreen';
import { ResultScreen } from '../screens/ResultScreen';
import { HistoricoScreen } from '../screens/HistoricoScreen';
import { VendaDetalheScreen } from '../screens/VendaDetalheScreen';
import { CupomScreen } from '../screens/CupomScreen';
import { RelatoriosScreen } from '../screens/RelatoriosScreen';
import { CaixaScreen } from '../screens/CaixaScreen';
import { AjustesScreen } from '../screens/AjustesScreen';
import { useAuth } from '../contexts/AuthContext';

export type RootStackParamList = {
  Bootstrap: undefined;
  Login: undefined;
  Main: undefined;
  Cart: undefined;
  Checkout: undefined;
  Result: { vendaId: number };
  VendaDetalhe: { vendaId: number };
  Cupom: { vendaId: number };
};

export type PdvTabParamList = {
  Pdv: undefined;
  Historico: undefined;
  Relatorios: undefined;
  Caixa: undefined;
  Ajustes: undefined;
};

const Stack = createNativeStackNavigator<RootStackParamList>();
const Tab = createBottomTabNavigator<PdvTabParamList>();

const MainTabs: React.FC = () => {
  return (
    <Tab.Navigator
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: colors.primary,
        tabBarInactiveTintColor: colors.muted,
        tabBarStyle: { backgroundColor: colors.surface, borderTopColor: colors.border },
      }}
    >
      <Tab.Screen
        name="Pdv"
        component={PdvScreen}
        options={{
          title: 'PDV',
          tabBarIcon: ({ color, size }) => <Ionicons name="grid" size={size} color={color} />,
        }}
      />
      <Tab.Screen
        name="Historico"
        component={HistoricoScreen}
        options={{
          title: 'Vendas',
          tabBarIcon: ({ color, size }) => <Ionicons name="receipt" size={size} color={color} />,
        }}
      />
      <Tab.Screen
        name="Relatorios"
        component={RelatoriosScreen}
        options={{
          title: 'Relatórios',
          tabBarIcon: ({ color, size }) => (
            <Ionicons name="bar-chart" size={size} color={color} />
          ),
        }}
      />
      <Tab.Screen
        name="Caixa"
        component={CaixaScreen}
        options={{
          title: 'Caixa',
          tabBarIcon: ({ color, size }) => <Ionicons name="cash" size={size} color={color} />,
        }}
      />
      <Tab.Screen
        name="Ajustes"
        component={AjustesScreen}
        options={{
          title: 'Ajustes',
          tabBarIcon: ({ color, size }) => <Ionicons name="settings" size={size} color={color} />,
        }}
      />
    </Tab.Navigator>
  );
};

export const AppNavigator: React.FC = () => {
  const { loading, token } = useAuth();

  return (
    <Stack.Navigator
      screenOptions={{
        headerShown: false,
        contentStyle: { backgroundColor: colors.cream },
      }}
    >
      {loading ? (
        <Stack.Screen name="Bootstrap" component={BootstrapScreen} />
      ) : token ? (
        <>
          <Stack.Screen name="Main" component={MainTabs} />
          <Stack.Screen
            name="Cart"
            component={CartScreen}
            options={{ headerShown: true, title: 'Carrinho', headerTintColor: colors.primaryDark }}
          />
          <Stack.Screen
            name="Checkout"
            component={CheckoutScreen}
            options={{ headerShown: true, title: 'Pagamento', headerTintColor: colors.primaryDark }}
          />
          <Stack.Screen
            name="Result"
            component={ResultScreen}
            options={{ headerShown: false, gestureEnabled: false }}
          />
          <Stack.Screen
            name="VendaDetalhe"
            component={VendaDetalheScreen}
            options={{ headerShown: true, title: 'Venda', headerTintColor: colors.primaryDark }}
          />
          <Stack.Screen
            name="Cupom"
            component={CupomScreen}
            options={{ headerShown: true, title: 'Cupom', headerTintColor: colors.primaryDark }}
          />
        </>
      ) : (
        <Stack.Screen name="Login" component={LoginScreen} />
      )}
    </Stack.Navigator>
  );
};
