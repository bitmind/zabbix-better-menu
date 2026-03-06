# A better Zabbix menu

A slightly improved version of Zabbix menu, with a task-centric structure.

## Goals
- Reduce the cognitive effort during menu navigation
- Simplify the learning curve for new users

## Design principles
- Reuse and reorganize existing menu items (we cannot rewrite the entire Zabbix frontend)
- Reduce the number of first-level sections
- Menu sections represent actions (View, Configure, Discover, etc.)
- Group together frequently used items
- Use icons whenever possible (currently chosen from the standard Zabbix icons)


## Menu sections
- **View** monitored environment
- **Configure** objects to be monitored
- **Discover** new objects on the network
- **Evaluate** services and SLA
- **React** with actions and scripts
- **Admin** Zabbix instance (users, proxies, frontend) and troubleshoot Zabbix issues.



## Detailed changes
- The Monitoring and Data Collection sections are renamed **View** and **Configure**. 
-  **Maps**: a new submenu includes the management of icons, backgrounds, and mapping rules. 
- **Web monitoring**: a new entry for direct access to web scenarios.
- The **Inventory** and **Reports** sections are merged into the View section. The Reports menu contains only reports related to monitored objects; reports regarding the status of Zabbix have been moved to the Admin section.
- A new **Discover** section groups together network discovery rules, discovery status, and discovery actions.
- The Services section is renamed **Evaluate** and also hosts service actions.
- The Alerts section is renamed **React**.
- The Administration section is renamed **Admin** and completely reorganized into submenus: Proxies, Global (macros, regexp, timeouts), Frontend, General, and System info. Also the Users section is merged into Admin.



