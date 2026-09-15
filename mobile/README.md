# ColoAfeto PDV — App Mobile

Aplicativo móvel (Android/iOS) do **PDV / venda balcão** da ColoAfeto. Ele se comunica
exclusivamente com o backend PHP em produção localizado em
`ColoAfeto/api/pdv/` (mesmas regras de negócio do PDV web em `admin/vendas/_pdv.php`):

- Venda com busca por produto, categoria e código/SKU
- Carrinho, desconto, troco e parcelamento (crédito / a prazo)
- Pagamentos: dinheiro, PIX (copia-e-cola + QR), cartão, a prazo (com parcelas mensais)
- Caixa PDV: abertura, sangria/suprimento e fechamento
- Histórico de vendas, cancelamento com estorno e cupom/recibo por e-mail
- Controle de permissões via token (usuário precisa ter permissão `vendas` e caixa aberto)

---

## 1. Estrutura

```
mobile/
├── App.tsx                     # Providers (Auth, Toast, Cart) + navegação
├── src/
│   ├── api/                    # client.ts (fetch + SecureStore) e endpoints.ts
│   ├── components/             # AppButton, AppInput, MoneyInput, ProductCard, Screen, SelectModal
│   ├── contexts/               # AuthContext, ToastContext, CartContext
│   ├── navigation/             # Stack raiz (Bootstrap/Login/Main) + BottomTabs (PDV, Vendas, Caixa, Ajustes)
│   ├── screens/                # Pdv, Cart, Checkout, Result, Historico, VendaDetalhe, Caixa, Ajustes
│   ├── types/                  # Tipos da API
│   ├── theme/                  # cores/paleta da marca
│   └── utils/format.ts         # máscaras de moeda/data pt-BR
```

Backend (fora do mobile): `ColoAfeto/api/pdv/*.php`
— `bootstrap.php`, `login.php`, `logout.php`, `me.php`, `produtos.php`,
`categorias.php`, `clientes.php`, `caixa.php`, `venda.php`, `historico.php`,
`cancelar.php`, `cupom.php`.

---

## 2. Requisitos (servidor)

- VPS com **Ubuntu + Apache2** e PHP 8.x (mod_rewrite ativo)
- MariaDB/MySQL com o banco do ColoAfeto já importado (é o mesmo banco usado
  pelo site e pelo PDV web; o app NÃO cria/moda schema)
- O backend deve estar publicado em `https://coloeafetooficial.com.br/api/pdv/`

> A base usada em produção é o banco **existente** do PDV web — o app só lê/escreve
> através da API PHP acima, seguindo as mesmas tabelas/regras.

---

## 3. Instalação (desenvolvedor)

Requires Node 20+ e a CLI Expo:

```bash
cd mobile
npm install
npx expo install --check        # alinha versões dos pacotes mais recentes
npx tsc --noEmit                # deve terminar sem erros (gate de qualidade)
npx expo start                  # rodar em desenvolvimento (QR Code no Expo Go)
```

Gerar APK instalável de produção (EAS; o perfil está em `eas.json`):

```bash
npx eas-cli build -p android --profile production
```

---

## 4. Apontando para a API de PRODUÇÃO

Por padrão o app usa `DEFAULT_API_URL` em `src/api/client.ts`, apontando para a
API HTTPS de produção. Para desenvolvimento local, informe o endereço da API na
tela de login ou em Ajustes.

Em produção **não é preciso recompilar**: você informa o endereço do servidor
na **tela de Login** (campo "Endereço do servidor"), que é salvo no SecureStore
do aparelho. O app passa a falar com:

```
https://coloeafetooficial.com.br/api/pdv
```

Para que todos os aparelhos já venham configurados com produção, altere a constante:

```ts
// src/api/client.ts
export const DEFAULT_API_URL =
  'https://coloeafetooficial.com.br/api/pdv';
```

Depois de salvo (SecureStore), o app restaura a sessão automaticamente na próxima
abertura (Bootstrap). Para trocar de servidor, use Ajustes → "Endereço do PDV".

### Credenciais

O login valida na API (`login.php`) com `e-mail` + `senha` de um usuário com
permissão **PDV (~vendas) no módulo vendas**. Se o caixa estiver fechado, é
necessário abri-lo na aba **Caixa** antes de registrar venda.

---

## 5. Fluxo básico de uso

1. **Login** — informe servidor (uma vez), e-mail e senha
2. **PDV** — busque o produto, toque no card para adicionar ao carrinho
3. **Carrinho** — ajuste quantidades/remova itens, veja subtotal
4. **Checkout** — método de pagamento, desconto, parcelas (a prazo), cliente,
   e e-mail do recibo; confirme a venda
5. **Resultado** — mostra PIX copia-e-cola/QR (quando aplicável) e resumo; depois
   é possível enviar o cupom por e-mail
6. **Caixa** — abrir/fechar, sangria/suprimento, conferir totais por método
7. **Historico/Vendas** — lista vendas e detalhes; cancela com estorno

---

## 6. Notas de implantação (VPS Ubuntu/Apache)

- Publique `api/pdv/` sob o DocumentRoot `/var/www/html/coloeafetooficial`.
- Garanta `AllowOverride All` no vhost do Apache para o `controle de acesso`/CORS
  já tratados em `bootstrap.php`.
- HTTPS obrigatório (o app exige `https://` em produção; cert. Let's Encrypt).
  O app recusa http com aviso para evitar tráfego inseguro de token.
- Nenhum `.env`/segredo no cliente: o token fica no **SecureStore** (iOS Keychain /
  Android Keystore); a URL do servidor também.

---

## 7. Verificação rápida

```bash
# 1) API responde?
curl -k https://coloeafetooficial.com.br/api/pdv/me.php

# 2) tsc limpo
cd mobile && npx tsc --noEmit
```
