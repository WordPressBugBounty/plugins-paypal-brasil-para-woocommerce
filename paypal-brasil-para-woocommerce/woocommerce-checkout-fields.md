# 🟦 WooCommerce Checkout Fields — Documentação Técnica Completa

Este documento descreve de forma detalhada os campos nativos do checkout do WooCommerce e os campos adicionais utilizados por plugins.

Essa documentação é essencial para:

- Integrações com gateways de pagamento.
- Plugins que dependem de dados do checkout.
- Normalização dos nomes de campos mesmo quando alterados por plugins.
- Depuração de problemas de checkout relacionados a campos ausentes ou renomeados.

## 📑 Índice

1. **Visão Geral**
2. **Estrutura do Checkout**
3. **JSON Completo (Billing + Shipping)**
4. **Referências e Notas Técnicas**

## 📘 Visão Geral

O WooCommerce define um conjunto fixo de campos nativos para billing e shipping.
Porém, no Brasil, é comum que lojas utilizem plugins que adicionam campos como:

- CPF / CNPJ
- Tipo de pessoa
- Número da residência
- Bairro

Esses campos **não existem no WooCommerce Core**, portanto precisam ser documentados como campos adicionais opcionais.

## 🧩 Estrutura do Checkout

O checkout do WooCommerce organiza seus campos desta forma:

- billing → Dados de cobrança
- shipping → Dados de entrega
- native_fields → Campos que pertencem ao WooCommerce
- brazilian_fields → Campos extras adicionados por plugins

## 🟦 JSON Completo (Billing + Shipping)

A seguir está o JSON completo usado como referência de mapeamento interno:

```
{
  "billing": {
    "native_fields": {
      "billing_first_name": {
        "type": "text",
        "label": "First name",
        "meta": "_billing_first_name",
        "required": true
      },
      "billing_last_name": {
        "type": "text",
        "label": "Last name",
        "meta": "_billing_last_name",
        "required": true
      },
      "billing_company": {
        "type": "text",
        "label": "Company",
        "meta": "_billing_company",
        "required": false
      },
      "billing_country": {
        "type": "select",
        "label": "Country / Region",
        "meta": "_billing_country",
        "required": true
      },
      "billing_address_1": {
        "type": "text",
        "label": "Address line 1",
        "meta": "_billing_address_1",
        "required": true
      },
      "billing_address_2": {
        "type": "text",
        "label": "Address line 2",
        "meta": "_billing_address_2",
        "required": false
      },
      "billing_city": {
        "type": "text",
        "label": "Town / City",
        "meta": "_billing_city",
        "required": true
      },
      "billing_state": {
        "type": "select",
        "label": "State / County",
        "meta": "_billing_state",
        "required": true
      },
      "billing_postcode": {
        "type": "text",
        "label": "Postcode / ZIP",
        "meta": "_billing_postcode",
        "required": true
      },
      "billing_phone": {
        "type": "tel",
        "label": "Phone",
        "meta": "_billing_phone",
        "required": true
      },
      "billing_email": {
        "type": "email",
        "label": "Email address",
        "meta": "_billing_email",
        "required": true
      }
    },
    "brazilian_fields": {
      "billing_persontype": {
        "type": "select",
        "label": "Person type",
        "meta": "_billing_persontype",
        "required": false,
        "options": {
          "1": "Pessoa Física",
          "2": "Pessoa Jurídica"
        }
      },
      "billing_cpf": {
        "type": "text",
        "label": "CPF",
        "meta": "_billing_cpf",
        "required": false
      },
      "billing_cnpj": {
        "type": "text",
        "label": "CNPJ",
        "meta": "_billing_cnpj",
        "required": false
      },
      "billing_company_id": {
        "type": "text",
        "label": "Company ID / IE",
        "meta": "_billing_company_id",
        "required": false
      },
      "billing_number": {
        "type": "text",
        "label": "Address number",
        "meta": "_billing_number",
        "required": false
      },
      "billing_neighborhood": {
        "type": "text",
        "label": "Neighborhood (Bairro)",
        "meta": "_billing_neighborhood",
        "required": false
      }
    }
  },
  "shipping": {
    "native_fields": {
      "shipping_first_name": {
        "type": "text",
        "label": "First name",
        "meta": "_shipping_first_name",
        "required": true
      },
      "shipping_last_name": {
        "type": "text",
        "label": "Last name",
        "meta": "_shipping_last_name",
        "required": true
      },
      "shipping_company": {
        "type": "text",
        "label": "Company",
        "meta": "_shipping_company",
        "required": false
      },
      "shipping_country": {
        "type": "select",
        "label": "Country / Region",
        "meta": "_shipping_country",
        "required": true
      },
      "shipping_address_1": {
        "type": "text",
        "label": "Address line 1",
        "meta": "_shipping_address_1",
        "required": true
      },
      "shipping_address_2": {
        "type": "text",
        "label": "Address line 2",
        "meta": "_shipping_address_2",
        "required": false
      },
      "shipping_city": {
        "type": "text",
        "label": "Town / City",
        "meta": "_shipping_city",
        "required": true
      },
      "shipping_state": {
        "type": "select",
        "label": "State / County",
        "meta": "_shipping_state",
        "required": true
      },
      "shipping_postcode": {
        "type": "text",
        "label": "Postcode / ZIP",
        "meta": "_shipping_postcode",
        "required": true
      }
    },
    "brazilian_fields": {
      "shipping_number": {
        "type": "text",
        "label": "Address number",
        "meta": "_shipping_number",
        "required": false
      },
      "shipping_neighborhood": {
        "type": "text",
        "label": "Neighborhood (Bairro)",
        "meta": "_shipping_neighborhood",
        "required": false
      },
      "shipping_persontype": {
        "type": "select",
        "label": "Person type",
        "meta": "_shipping_persontype",
        "required": false,
        "options": {
          "1": "Pessoa Física",
          "2": "Pessoa Jurídica"
        }
      },
      "shipping_cpf": {
        "type": "text",
        "label": "CPF",
        "meta": "_shipping_cpf",
        "required": false
      },
      "shipping_cnpj": {
        "type": "text",
        "label": "CNPJ",
        "meta": "_shipping_cnpj",
        "required": false
      }
    }
  }
}
```

## 📎 Referências e Notas Técnicas

- WooCommerce Core: class-wc-checkout.php
- WC Order API: class-wc-order.php
- Plugin Brazilian Market for WooCommerce:
    - [woocommerce-extra-checkout-fields-for-brazil](https://wordpress.org/plugins/woocommerce-extra-checkout-fields-for-brazil/)