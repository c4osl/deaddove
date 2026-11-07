import wordpress from "@wordpress/eslint-plugin";
import globals from "globals";

export default [
	...wordpress.configs.recommended,
	{
		languageOptions: {
			globals: {
				...globals.browser,
				jQuery: "readonly",
				deaddove_ajax: "readonly",
			},
		},
		rules: {
			camelcase: ["error", { properties: "never", ignoreGlobals: true }],
			"no-console": ["error", { allow: ["error", "warn"] }],
		},
	},
	{
		ignores: ["node_modules/"],
	},
];
