/* Pandora agent service for Win32.
   
   Copyright (c) 2006-2021 Artica ST.
   Written by Esteban Sanchez.
  
   This program is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; either version 2, or (at your option)
   any later version.
  
   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.
  
   You should have received a copy of the GNU General Public License along
   with this program; if not, write to the Free Software Foundation,
   Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
*/

#ifndef	__PANDORA_WINDOWS_SERVICE_H__
#define	__PANDORA_WINDOWS_SERVICE_H__

#include <list>
#include <time.h>
#include "windows_service.h"
#include "pandora_agent_conf.h"
#include "modules/pandora_module_list.h"
#include "ssh/pandora_ssh_client.h"

#define FTP_DEFAULT_PORT 21
#define SSH_DEFAULT_PORT 22
#define SECONDARY_DIR "secondary" /* Path of the secondary buffer relative to the primary buffer. */

using namespace std;
using namespace Pandora_Modules;

namespace Pandora {
	/**
	 * Class to implement the Pandora Windows service.
	 */
	class Pandora_Windows_Service : public Windows_Service {
		typedef int (Pandora::Pandora_Windows_Service::*copy_func_p)(string, bool);
	private:
		Pandora_Agent_Conf  *conf;
		Pandora_Module_List *modules;
		Pandora_Module_List *broker_modules;
		long                 execution_number;
		string               agent_name;
		string               alias;
		time_t               timestamp;
		time_t               run_time;
		bool                 started;
		void                 *udp_server;
		bool                 tentacle_proxy;
		list<string> collection_disk;
		
		string        getXmlHeader    ();
		int           copyDataFile    (string filename, bool secondary_buffer = false);
		int           copyToSecondary (string filename, bool secondary_buffer = true);
		string        getValueFromCmdExec (string cmd_exec, int timeout);
		string        getAgentNameFromCmdExec (string cmd_exec);
		string        getCoordinatesFromCmdExec (string cmd_exec);
		int           copyTentacleDataFile (string host,
						     string filename,
						     string port,
						     string ssl,
						     string pass,
						     string opts,
						     int timeout);
		int           copyScpDataFile (string host,
						string remote_path,
						string filename);
		int           copyFtpDataFile (string host,
						string remote_path,
						string filename,
					        string password,
					        int timeout);
		int           copyLocalDataFile (string remote_path,
						string filename);
		void           recvDataFile (string filename);
		void           recvTentacleDataFile (string host,
						     string filename,
						     int timeout);

		int	       unzipCollection(string zip_path, string dest_dir);
		void	       checkCollections ();
		void		   addCollectionsPath();
		string         checkAgentName(string filename);
		int           checkConfig (string file);
		void		 purgeDiskCollections ();
		void           pandora_init_broker (string file_conf);
		void           pandora_run_broker (string config, long executions=0);
		int 		   count_broker_agents();
		void 		   check_broker_agents(string *all_conf);
		int 		   launchTentacleProxy();
		int				killTentacleProxy();
		
		Pandora_Windows_Service     ();

	public:
		void           pandora_run  (int forced_run);
		void           pandora_run  ();
		void           pandora_init ();
		void           pandora_init (bool reload_modules);
		
		long           interval;
		long           interval_sec;
		long           intensive_interval;
	public:
		static Pandora_Windows_Service *getInstance ();
		
		~Pandora_Windows_Service    ();
		
		void           setValues    (const char *svc_name,
					     const char *svc_display_name,
					     const char *svc_description);
		
		void           start        ();
		int            sendXml      (Pandora_Module_List *modules, string extra = "");
        void           sendBufferedXml (string path, copy_func_p copy_func, bool secondary_buffer);
		Pandora_Agent_Conf *getConf ();
		string         getEHKey (string ehorus_conf);
		long           getInterval ();
		long           getIntensiveInterval ();
		string         generateAgentName ();
		bool           writeToBuffer (string temporal);

	};
}

#endif
